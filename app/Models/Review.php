<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'reviews';

    protected $fillable = [
        'toilet_id',
        'user_id',
        'rating',
        'total_toilet',
        'cleanness',
        'facility',
        'environment',
        'crowded',
        'url_img',
        'description',


    ];

    protected $hidden = ['deleted_at'];

    public function toilet()
    {
        return $this->belongsTo(Toilet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewImages()
    {
        return $this->hasMany(ReviewImages::class);
    }
}
