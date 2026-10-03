<?php

namespace App\Models;

use App\Services\Crypto\KeyManager;
use App\Support\ComplaintStatus;
use Illuminate\Database\Eloquent\Model;

class ComplaintStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['complaint_id', 'from_status', 'to_status', 'changed_by', 'note', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'changed_by', 'user_id');
    }

    public function setNoteAttribute(?string $value): void
    {
        $this->attributes['note'] = ($value === null || $value === '') ? null : app(KeyManager::class)->seal($value);
    }

    public function getNoteAttribute(): string
    {
        try {
            return app(KeyManager::class)->open($this->getRawOriginal('note'));
        } catch (\Throwable $e) {
            return '[unable to decrypt]';
        }
    }

    public function toLabel(): string
    {
        return ComplaintStatus::label($this->to_status);
    }
}
