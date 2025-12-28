<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'brand_id',
        'code',
        'name',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
