<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToiletFavorite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'favorite_toilets';
    protected $fillable = [
        'toilet_id',
        'user_id',
    ];

    protected $hidden = ['deleted_at'];

    public function toilet()
    {
        return $this->belongsTo(Toilet::class)->with(['owner','category','checkins','reviews','toiletHours','toiletImage']);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
