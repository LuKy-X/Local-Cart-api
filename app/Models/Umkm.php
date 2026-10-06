<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Umkm extends Model
{
    protected $fillable = [
        'user_id',
        'kecamatan_id',
        'nama_umkm',
        'deskripsi',
        'alamat',
        'telepon',
        'foto_logo',
        'is_approved',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class);
    }

    public function activeProducts()
    {
        return $this->hasMany(Product::class)->where('is_active', true);
    }

    public function getAverageRatingAttribute()
    {
        // Hitung rata-rata rating dari semua produk UMKM
        $totalRating = 0;
        $totalReviews = 0;

        foreach ($this->products as $product) {
            $totalRating += $product->ratings()->avg('rating') * $product->ratings()->count();
            $totalReviews += $product->ratings()->count();
        }

        return $totalReviews > 0 ? $totalRating / $totalReviews : 0;
    }

    public function getTotalRatingsAttribute()
    {
        // Hitung total ulasan dari semua produk UMKM
        $total = 0;
        foreach ($this->products as $product) {
            $total += $product->ratings()->count();
        }
        return $total;
    }

    public function getTotalSoldAttribute()
    {
        // Hitung total produk terjual
        $total = 0;
        foreach ($this->products as $product) {
            $total += $product->orderItems()->sum('quantity');
        }
        return $total;
    }

    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    }
}
