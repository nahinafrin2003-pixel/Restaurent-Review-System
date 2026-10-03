<?php

namespace App\Models;

use App\Services\Crypto\KeyManager;
use App\Services\Crypto\HMAC;
use App\Services\Crypto\Asymmetric\ECC;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'reviews';
    protected $primaryKey = 'review_id';
    protected $keyType = 'int';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'restaurant_name',
        'restaurant_id',
        'review_text',
        'review_score',
        'location',
        'city',
        'status',
        'edit_count',
        'max_edit_limit',
        'signature',
        'mac',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'signature' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function postedReviews()
    {
        return $this->hasMany(PostedReview::class, 'review_id', 'review_id');
    }

    public function adminActions()
    {
        return $this->hasMany(AdminAction::class, 'review_id', 'review_id');
    }

    // Encryption/Decryption for review_text
    public function setReviewTextAttribute(string $value): void
    {
        $this->attributes['review_text'] = app(KeyManager::class)->encryptUserField($value);
    }

    public function getReviewTextAttribute(): string
    {
        $encrypted = $this->getRawOriginal('review_text');
        
        if (empty($encrypted)) {
            return '';
        }

        try {
            return app(KeyManager::class)->decrypt($encrypted);
        } catch (\Exception $e) {
            \Log::warning('Review text decryption failed: ' . $e->getMessage());
            return '';
        }
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    protected function integrityPayload(): string
    {
        return json_encode([
            'review_id' => $this->review_id,
            'user_id' => $this->user_id,
            'restaurant_name' => $this->restaurant_name,
            'review_text' => $this->review_text,
            'review_score' => $this->review_score,
            'city' => $this->city,
        ]);
    }

    /** HMAC-SHA256 + ECDSA (secp256k1) verification — run on every read. */
    public function verifyIntegrity(): bool
    {
        $message = $this->integrityPayload();
        $km = app(KeyManager::class);

        if (! empty($this->mac) && ! HMAC::verify($message, config('crypto.root_secret'), $this->mac)) {
            return false;
        }

        if (! empty($this->signature)) {
            return $km->verify($message, $this->signature);
        }

        return ! empty($this->mac);
    }

    public function generateMac(): void
    {
        $this->mac = HMAC::sign($this->integrityPayload(), config('crypto.root_secret'));
    }

    public function generateSignature(): void
    {
        $this->signature = app(KeyManager::class)->sign($this->integrityPayload());
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'wait' => 'Waitlisted',
            default => ucfirst((string) $this->status),
        };
    }
}
