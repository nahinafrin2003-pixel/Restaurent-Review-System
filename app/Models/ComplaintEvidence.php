<?php

namespace App\Models;

use App\Services\Crypto\KeyManager;
use Illuminate\Database\Eloquent\Model;

class ComplaintEvidence extends Model
{
    protected $table = 'complaint_evidence';
    public $timestamps = false;

    protected $fillable = [
        'complaint_id', 'uploaded_by', 'type', 'original_name', 'stored_path', 'mime', 'size', 'sha256',
        'blur_score', 'is_blurry', 'ocr_text', 'ocr_restaurant', 'ocr_date', 'ocr_amount', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime', 'is_blurry' => 'boolean', 'blur_score' => 'float'];

    public const TYPES = [
        'image' => 'Photo',
        'video' => 'Video',
        'audio' => 'Audio',
        'receipt' => 'Receipt',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function setOriginalNameAttribute(string $value): void
    {
        $this->attributes['original_name'] = app(KeyManager::class)->seal($value);
    }

    public function getOriginalNameAttribute(): string
    {
        try {
            return app(KeyManager::class)->open($this->getRawOriginal('original_name'));
        } catch (\Throwable $e) {
            return 'evidence-' . $this->id;
        }
    }

    public function setOcrTextAttribute(?string $value): void
    {
        $this->attributes['ocr_text'] = ($value === null || $value === '') ? null : app(KeyManager::class)->seal($value);
    }

    public function getOcrTextAttribute(): string
    {
        try {
            return app(KeyManager::class)->open($this->getRawOriginal('ocr_text'));
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function humanSize(): string
    {
        $size = (int) $this->size;

        return $size >= 1048576 ? round($size / 1048576, 1) . ' MB' : max(1, round($size / 1024)) . ' KB';
    }
}
