<?php

namespace App\Http\Controllers\api;

use App\Models\Umkm;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;

class HomeController extends Controller
{
    public function index()
    {
        $featuredProducts = Product::with(['umkm', 'category'])
            ->active()
            ->whereExists(function ($query) {
                $query->selectRaw('AVG(rating) as avg_rating')
                    ->from('ratings')
                    ->whereColumn('product_id', 'products.id')
                    ->groupBy('product_id')
                    ->havingRaw('AVG(rating) >= 4.0');
            })
            ->orderByRaw('(SELECT AVG(rating) FROM ratings WHERE product_id = products.id) DESC')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $newProducts = Product::with(['umkm', 'category'])
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $popularProducts = Product::with(['umkm', 'category'])
            ->active()
            ->withCount(['orderItems as recent_sales' => function($query) {
                $query->whereHas('order', function($q) {
                    $q->where('created_at', '>=', now()->subDays(30));
                });
            }])
            ->orderBy('recent_sales', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $mostViewedProducts = Product::with(['umkm', 'category'])
            ->active()
            ->withCount('views')
            ->orderBy('views_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $categories = Category::withCount('products')
                        ->latest()
                        ->get();

        $umkmCount = Umkm::where('is_approved', true)->count();
        $productCount = Product::where('is_active', 1)->count();
        $categoryCount = Category::count();

        return response()->json([
            'featured_products' => ProductResource::collection($featuredProducts),
            'new_products' => ProductResource::collection($newProducts),
            'popular_products' => ProductResource::collection($popularProducts),
            'most_viewed_products' => ProductResource::collection($mostViewedProducts),
            'categories' => $categories,
            'umkm_count' => $umkmCount,
            'product_count' => $productCount,
            'category_count' => $categoryCount,
        ]);
    }
}
