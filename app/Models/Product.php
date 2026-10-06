<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'umkm_id',
        'category_id',
        'nama_produk',
        'deskripsi',
        'harga',
        'stok',
        'foto',
        'is_active',
    ];

    protected $appends = [
        'average_rating',
        'total_views',
        'total_ratings',
        'sales_count',
    ];

    public function umkm()
    {
        return $this->belongsTo(Umkm::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function views()
    {
        return $this->hasMany(ProductView::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class);
    }

    public function getAverageRatingAttribute()
    {
        return $this->ratings()->avg('rating') ?: 0;
    }

    public function getTotalViewsAttribute()
    {
        return $this->views()->count();
    }

    public function getTotalRatingsAttribute()
    {
        return $this->ratings()->count();
    }

    public function getSalesCountAttribute()
    {
        return $this->orderItems()->sum('quantity');
    }

    // Scope untuk produk aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope untuk produk dengan rating tinggi
    public function scopeHighRated($query, $minRating = 4.0)
    {
        return $query->whereHas('ratings', function ($q) use ($minRating) {
            $q->selectRaw('AVG(rating) as avg_rating')
              ->havingRaw('AVG(rating) >= ?', [$minRating])
              ->groupBy('product_id');
        });
    }

    // Scope untuk produk terlaris
    public function scopeBestSelling($query, $days = 30)
    {
        return $query->whereHas('orderItems.order', function ($q) use ($days) {
            $q->where('created_at', '>=', now()->subDays($days));
        })->withCount(['orderItems as recent_sales' => function ($q) use ($days) {
            $q->whereHas('order', function ($q) use ($days) {
                $q->where('created_at', '>=', now()->subDays($days));
            });
        }])->orderBy('recent_sales', 'desc');
    }


    public function getImageUrlAttribute()
    {
        if ($this->foto) {
            return Storage::url($this->foto);
        }
        return asset('images/default-product.webp4');
    }

    public function getUmkmNameAttribute()
    {
        return $this->umkm ? $this->umkm->nama_umkm : 'UMKM Lokal';
    }

    public function getCategoryNameAttribute()
    {
        return $this->category ? $this->category->nama_kategori : 'Kategori Lain';
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->harga, 0, ',', '.');
    }

    // Scope untuk produk dari UMKM yang sama
    public function scopeFromSameUmkm($query, $umkmId, $excludeId = null)
    {
        $query->where('umkm_id', $umkmId)
            ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }

    // Scope untuk produk dari kategori yang sama
    public function scopeFromSameCategory($query, $categoryId, $excludeId = null)
    {
        $query->where('category_id', $categoryId)
            ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query;
    }
}
