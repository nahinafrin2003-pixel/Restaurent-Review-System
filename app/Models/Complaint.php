<?php

namespace App\Models;

use App\Services\Crypto\KeyManager;
use App\Services\Crypto\SHA256;
use App\Support\ComplaintCategory;
use App\Support\ComplaintStatus;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = [
        'reference', 'public_token', 'user_id', 'restaurant_id', 'category', 'auto_category', 'category_confidence',
        'title', 'description', 'incident_date', 'district', 'status', 'assigned_officer_id', 'duplicate_of_id',
        'outcome', 'mac', 'signature', 'report_sha256', 'report_generated_at', 'verified_at', 'resolved_at',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'signature' => 'array',
        'verified_at' => 'datetime',
        'resolved_at' => 'datetime',
        'report_generated_at' => 'datetime',
    ];

    /* ---------------- relationships ---------------- */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function officer()
    {
        return $this->belongsTo(User::class, 'assigned_officer_id', 'user_id');
    }

    public function history()
    {
        return $this->hasMany(ComplaintStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function evidence()
    {
        return $this->hasMany(ComplaintEvidence::class)->orderBy('id');
    }

    public function messages()
    {
        return $this->hasMany(ComplaintMessage::class)->orderBy('id');
    }

    public function duplicateCandidates()
    {
        return $this->hasMany(ComplaintDuplicate::class)->orderByDesc('score');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(Complaint::class, 'duplicate_of_id');
    }

    /* ---------------- encrypted attributes (RSA-OAEP) ---------------- */

    public function setTitleAttribute(string $value): void
    {
        $this->attributes['title'] = app(KeyManager::class)->encrypt($value);
    }

    public function getTitleAttribute(): string
    {
        return $this->safeDecrypt('title');
    }

    public function setDescriptionAttribute(string $value): void
    {
        $this->attributes['description'] = app(KeyManager::class)->encrypt($value);
    }

    public function getDescriptionAttribute(): string
    {
        return $this->safeDecrypt('description');
    }

    protected function safeDecrypt(string $column): string
    {
        try {
            return app(KeyManager::class)->decrypt($this->getRawOriginal($column) ?? $this->attributes[$column] ?? '');
        } catch (\Throwable $e) {
            \Log::warning("Complaint {$this->id} {$column} decryption failed: " . $e->getMessage());

            return '[unable to decrypt]';
        }
    }

    /* ---------------- integrity (HMAC + ECDSA) ---------------- */

    /** Canonical message covering every security-relevant field (plaintext values). */
    public function canonicalPayload(): string
    {
        return json_encode([
            'id' => $this->id,
            'reference' => $this->reference,
            'user_id' => (int) $this->user_id,
            'restaurant_id' => (int) $this->restaurant_id,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'incident_date' => optional($this->incident_date)->format('Y-m-d'),
            'district' => $this->district,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function seal(): void
    {
        $km = app(KeyManager::class);
        $payload = $this->canonicalPayload();
        $this->mac = $km->mac($payload);
        $this->signature = $km->sign($payload);
    }

    public function macValid(): bool
    {
        return app(KeyManager::class)->verifyMac($this->canonicalPayload(), $this->mac);
    }

    public function signatureValid(): bool
    {
        return app(KeyManager::class)->verify($this->canonicalPayload(), $this->signature);
    }

    /** Full tamper check: HMAC and ECC signature. */
    public function integrity(): array
    {
        $mac = $this->macValid();
        $sig = $this->signatureValid();

        return ['mac' => $mac, 'signature' => $sig, 'ok' => $mac && $sig];
    }

    public function contentHash(): string
    {
        return SHA256::hash($this->canonicalPayload());
    }

    /* ---------------- helpers ---------------- */

    public function statusLabel(): string
    {
        return ComplaintStatus::label($this->status);
    }

    public function categoryLabel(): string
    {
        return ComplaintCategory::label($this->category);
    }

    public function isVerified(): bool
    {
        return in_array($this->status, ComplaintStatus::VERIFIED_STATES, true)
            || ($this->status === ComplaintStatus::CLOSED && $this->verified_at !== null);
    }

    public function acceptsEvidence(): bool
    {
        return ComplaintStatus::acceptsEvidence($this->status);
    }

    public function publicUrl(): string
    {
        return url('/verify/' . $this->reference . '?t=' . $this->public_token);
    }

    public static function makeReference(int $id, ?int $year = null): string
    {
        return sprintf('CMP-%d-%06d', $year ?? (int) now()->format('Y'), $id);
    }

    public static function makePublicToken(string $reference): string
    {
        return substr(\App\Services\Crypto\HMAC::sign('public-verify|' . $reference, (string) config('crypto.root_secret')), 0, 24);
    }
}
