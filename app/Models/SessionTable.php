<?php

namespace App\Models;

use App\Services\Crypto\KeyManager;
use Illuminate\Database\Eloquent\Model;

class SessionTable extends Model
{
    protected $table = 'session_table';
    protected $primaryKey = 'session_id';
    protected $keyType = 'int';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token',
        'created_at',
        'expiry_time',
        'last_activity',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'expiry_time' => 'datetime',
        'last_activity' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    protected static function hashToken(string $token): string
    {
        return \App\Services\Crypto\SHA256::hash($token);
    }

    public function setTokenAttribute(string $value): void
    {
        $this->attributes['token'] = self::hashToken($value);
    }

    public function getTokenAttribute(): string
    {
        return $this->getRawOriginal('token') ?: '';
    }

    public static function findByToken(string $token): ?self
    {
        $hashedToken = self::hashToken($token);
        return self::where('token', $hashedToken)->first();
    }

    public static function deleteByToken(string $token): void
    {
        $hashedToken = self::hashToken($token);
        self::where('token', $hashedToken)->delete();
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expiry_time);
    }

    public function isActive(): bool
    {
        return !$this->isExpired();
    }
}
