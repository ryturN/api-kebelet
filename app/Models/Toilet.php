<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Toilet extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'toilets';
    protected $guarded = 'id';

    protected $fillable = [
        'owner_id',
        'location_id',
        'name',
        'grade',
        'address',
        'latitude',
        'longitude'
    ];

    protected $hidden = ['deleted_at'];

    public function owner()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }

    // public function checkins()
    // {
    //     return $this->belongsToMany(User::class, 'checkins')->withPivot('id', 'point', 'created_at')->wherePivot('deleted_at', null);
    // }

    public function checkins()
    {
        return $this->hasMany(Checkin::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function toiletHours()
    {
        return $this->hasMany(ToiletHours::class);
    }

    public function toiletImage()
    {
        return $this->hasMany(ToiletImage::class);
    }

    public function visitHours()
    {
        return $this->hasMany(ToiletVisit::class);
    }

    public function favorite()
    {
        return $this->hasMany(ToiletFavorite::class);
    }
}
