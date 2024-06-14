<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checkin extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'checkins';

    protected $fillable = [
        'user_id',
        'toilet_id',
        'point'
    ];

    protected $hidden = ['deleted_at'];
}
