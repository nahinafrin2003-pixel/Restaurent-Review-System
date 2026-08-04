<?php

namespace App\Models;

use App\Services\Crypto\KeyManager;
use Illuminate\Database\Eloquent\Model;

class PostedReview extends Model
{
    protected $table = 'posted_reviews';
    protected $primaryKey = 'post_id';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'review_id',
        'user_id',
        'encrypted_review',
        'posted_at',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class, 'review_id', 'review_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public static function createFromReview(Review $review): self
    {
        if ($review->status !== 'approved') {
            throw new \LogicException('Only approved reviews may be stored in posted_reviews.');
        }

        return static::firstOrCreate([
            'review_id' => $review->review_id,
        ], [
            'user_id' => $review->user_id,
            'encrypted_review' => json_encode($review->toArray()),
            'posted_at' => now(),
        ]);
    }

    public function setEncryptedReviewAttribute(string $value): void
    {
        $this->attributes['encrypted_review'] = app(KeyManager::class)->encryptUserField($value);
    }

    public function getEncryptedReviewAttribute(): string
    {
        $encrypted = $this->getRawOriginal('encrypted_review');
        
        if (empty($encrypted)) {
            return '';
        }

        try {
            return app(KeyManager::class)->decryptUserField($encrypted);
        } catch (\Exception $e) {
            \Log::warning('Posted review decryption failed: ' . $e->getMessage());
            return '';
        }
    }
}
