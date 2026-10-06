<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductViewResource;

class ProductViewController extends Controller
{
    public function index()
    {
        $views = ProductView::with(['product.umkm', 'customer.user'])
                           ->latest()
                           ->paginate(12);

        return ProductViewResource::collection($views);
    }

    public function productViews(Product $product)
    {
        $views = $product->views()
                        ->with(['customer.user'])
                        ->latest()
                        ->paginate(12);

        return ProductViewResource::collection($views);
    }

    public function analytics()
    {
        $totalViews = ProductView::count();
        $uniqueVisitors = ProductView::distinct('ip_address')->count('ip_address');
        $mostViewedProducts = ProductView::selectRaw('product_id, count(*) as views')
                                        ->with('product')
                                        ->groupBy('product_id')
                                        ->orderBy('views', 'desc')
                                        ->limit(10)
                                        ->get();

        return response()->json([
            'total_views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'most_viewed_products' => $mostViewedProducts,
        ]);
    }
}
