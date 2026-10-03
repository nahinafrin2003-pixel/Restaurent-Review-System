<?php

namespace App\Models;

use App\Services\Crypto\SHA256;
use App\Support\Districts;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    protected $fillable = ['name', 'address', 'district', 'latitude', 'longitude', 'trust_score', 'verified_complaints', 'created_by'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'trust_score' => 'float',
    ];

    public function setNameAttribute(string $value): void
    {
        $value = trim(preg_replace('/\s+/', ' ', $value));
        $this->attributes['name'] = $value;
        $this->attributes['name_hash'] = SHA256::hash(mb_strtolower($value));
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'restaurant_id');
    }

    public static function findByName(string $name, string $district): ?self
    {
        $hash = SHA256::hash(mb_strtolower(trim(preg_replace('/\s+/', ' ', $name))));

        return self::where('name_hash', $hash)->where('district', $district)->first();
    }

    /** Coordinates for maps; falls back to the district centre. */
    public function coordinates(): array
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return [(float) $this->latitude, (float) $this->longitude];
        }

        return Districts::centre($this->district);
    }

    /** 0-5 rendered as filled/half/empty stars. */
    public function stars(): string
    {
        $score = (float) $this->trust_score;
        $full = (int) floor($score);
        $half = ($score - $full) >= 0.5 ? 1 : 0;

        return str_repeat('★', $full) . ($half ? '⯪' : '') . str_repeat('☆', 5 - $full - $half);
    }

    public function trustLevel(): string
    {
        return match (true) {
            $this->trust_score >= 4.0 => 'good',
            $this->trust_score >= 2.5 => 'warn',
            default => 'bad',
        };
    }
}
