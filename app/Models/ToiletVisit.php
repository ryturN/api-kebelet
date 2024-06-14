<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToiletVisit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'toilet_visits';
    protected $guarded = ['id'];
    protected $fillable = [
        'toilet_id',
        'user_id',
        'ip_address',
        'visited_at',
        'last_visited_at',
    ];

    protected $hidden = ['deleted_at'];

    protected $casts = [
        'last_visited_at' => 'datetime'
    ];

    public function toilet()
    {
        return $this->belongsTo(Toilet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
