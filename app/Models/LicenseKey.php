<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseKey extends Model
{
    protected $fillable = [
        'brand_id',
        'customer_email',
        'key',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function activations()
    {
        return $this->hasMany(Activation::class);
    }
}
