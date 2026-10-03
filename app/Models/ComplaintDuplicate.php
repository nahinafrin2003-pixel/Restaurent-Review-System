<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintDuplicate extends Model
{
    protected $fillable = ['complaint_id', 'candidate_id', 'score', 'status', 'reviewed_by'];

    protected $casts = ['score' => 'float'];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function candidate()
    {
        return $this->belongsTo(Complaint::class, 'candidate_id');
    }
}
