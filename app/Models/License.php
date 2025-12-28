<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $fillable = [
        'license_key_id',
        'product_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function licenseKey()
    {
        return $this->belongsTo(LicenseKey::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
