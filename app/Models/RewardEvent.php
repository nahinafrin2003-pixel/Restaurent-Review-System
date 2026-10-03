<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'points', 'reason', 'complaint_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
