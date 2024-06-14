<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PointHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'point_histories';

    protected $fillable = [
        'user_id',
        'points',
        'type',
        'occurred_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
