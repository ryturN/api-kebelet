<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToiletImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'toilet_images';
    protected $guarded = 'id';

    protected $fillable = [
        'toilet_id',
        'url',
        'sequence'
    ];

    protected $hidden = ['deleted_at'];

    public function toilet()
    {
        return $this->belongsTo(Toilet::class);
    }
}
