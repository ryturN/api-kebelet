<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewImages extends Model
{
    use HasFactory,SoftDeletes;
    protected $table ='review_images';

    protected $fillable = [
        'review_id',
        'url_img',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

}
