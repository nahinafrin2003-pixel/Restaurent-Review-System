<?php

namespace App\Models;

use App\Services\Crypto\KeyManager;
use Illuminate\Database\Eloquent\Model;

class ComplaintMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['complaint_id', 'sender_id', 'body', 'mac', 'read_at', 'created_at'];

    protected $casts = ['created_at' => 'datetime', 'read_at' => 'datetime'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    public function setBodyAttribute(string $value): void
    {
        $km = app(KeyManager::class);
        $this->attributes['body'] = $km->seal($value);
        $this->attributes['mac'] = $km->mac($this->macPayload($value));
    }

    public function getBodyAttribute(): string
    {
        try {
            return app(KeyManager::class)->open($this->getRawOriginal('body'));
        } catch (\Throwable $e) {
            return '[unable to decrypt]';
        }
    }

    protected function macPayload(string $body): string
    {
        return json_encode(['complaint' => (int) ($this->attributes['complaint_id'] ?? 0), 'sender' => (int) ($this->attributes['sender_id'] ?? 0), 'body' => $body]);
    }

    public function macValid(): bool
    {
        return app(KeyManager::class)->verifyMac($this->macPayload($this->body), $this->mac);
    }
}
