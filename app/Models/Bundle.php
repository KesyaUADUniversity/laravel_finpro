<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'total_price',
        'original_price',
        'savings',
        'is_active'
    ];

    protected $casts = [
        'total_price' => 'integer',
        'original_price' => 'integer',
        'savings' => 'integer',
        'is_active' => 'boolean'
    ];

    // Relasi ke produk (many-to-many)
    public function products()
    {
        return $this->belongsToMany(Product::class, 'bundle_product')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
}