<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToiletHours extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'toilet_hours';
    protected $guarded = 'id';
    
    protected $fillable = [
        'toilet_id',
        'day',
        'hour_open',
        'hour_close'
    ];

    protected $hidden = ['deleted_at'];

    public function toilet()
    {
        return $this->belongsTo(Toilet::class);
    }
}
