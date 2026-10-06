<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Umkm;
use App\Models\Category;
use App\Models\Kecamatan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UmkmResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\KecamatanResource;

class SearchController extends Controller
{
    /**
     * Search products
     */
    public function searchProducts(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $query = Product::with(['umkm', 'category', 'umkm.kecamatan'])
            ->where('is_active', true)
            ->where('stok', '>', 0);

        // Search query
        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('nama_produk', 'like', '%' . $searchTerm . '%')
                  ->orWhere('deskripsi', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('umkm', function($umkmQuery) use ($searchTerm) {
                      $umkmQuery->where('nama_umkm', 'like', '%' . $searchTerm . '%');
                  })
                  ->orWhereHas('category', function($categoryQuery) use ($searchTerm) {
                      $categoryQuery->where('nama_kategori', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // Filter by categories
        if ($request->has('categories')) {
            $categories = $request->categories;
            if (is_array($categories)) {
                $query->whereIn('category_id', $categories);
            } else {
                $query->where('category_id', $categories);
            }
        }

        // Filter by UMKM kecamatan
        if ($request->has('kecamatans')) {
            $kecamatanIds = $request->kecamatans;
            if (is_array($kecamatanIds) && count($kecamatanIds)) {
                $query->whereHas('umkm', function ($q) use ($kecamatanIds) {
                    $q->whereIn('kecamatan_id', $kecamatanIds);
                });
            }
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('harga', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('harga', '<=', $request->max_price);
        }

        // Filter in stock only
        if ($request->boolean('in_stock')) {
            $query->where('stok', '>', 0);
        }

        // Sorting
        switch ($request->get('sort_by', 'relevance')) {
            case 'price_low':
                $query->orderBy('harga', 'asc');
                break;
            case 'price_high':
                $query->orderBy('harga', 'desc');
                break;
            case 'rating':
                $query->orderByRaw('(
                    SELECT AVG(r.rating)
                    FROM ratings r
                    WHERE r.product_id = products.id
                    AND r.is_approved = 1
                ) DESC')
                ->orderBy('created_at', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default: // relevance
                if ($request->has('q')) {
                    $searchTerm = $request->q;
                    $query->orderByRaw("
                        CASE
                            WHEN nama_produk LIKE ? THEN 1
                            WHEN nama_produk LIKE ? THEN 2
                            WHEN deskripsi LIKE ? THEN 3
                            ELSE 4
                        END
                    ", [
                        $searchTerm . '%',
                        '%' . $searchTerm . '%',
                        '%' . $searchTerm . '%'
                    ]);
                }
                $query->orderBy('created_at', 'desc');
                break;
        }

        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
            'meta' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    /**
     * Search UMKM
     */
    public function searchUmkms(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $query = Umkm::with(['user', 'kecamatan'])
            ->withCount(['products' => function($q) {
                $q->where('is_active', true);
            }]);

        // Search query
        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('nama_umkm', 'like', '%' . $searchTerm . '%')
                  ->orWhere('deskripsi', 'like', '%' . $searchTerm . '%')
                  ->orWhere('alamat', 'like', '%' . $searchTerm . '%')
                  ->orWhere('telepon', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('kecamatan', function($kecamatanQuery) use ($searchTerm) {
                      $kecamatanQuery->where('nama_kecamatan', 'like', '%' . $searchTerm . '%');
                  })
                  ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // Filter by kecamatan
        if ($request->has('kecamatans')) {
            $kecamatanIds = $request->kecamatans;
            if (is_array($kecamatanIds) && count($kecamatanIds)) {
                $query->whereIn('kecamatan_id', $kecamatanIds);
            }
        }

        // Filter by approval status
        if ($request->has('is_approved')) {
            $isApproved = $request->input('is_approved');
            if ($isApproved === 'true' || $isApproved === true) {
                $query->where('is_approved', true);
            } elseif ($isApproved === 'false' || $isApproved === false) {
                $query->where('is_approved', false);
            }
        }

        // Sorting
        switch ($request->get('sort_by', 'relevance')) {
            case 'name_asc':
                $query->orderBy('nama_umkm', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('nama_umkm', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'product_count':
                $query->orderBy('products_count', 'desc');
                break;
            default: // relevance
                if ($request->has('q')) {
                    $searchTerm = $request->q;
                    $query->orderByRaw("
                        CASE
                            WHEN nama_umkm LIKE ? THEN 1
                            WHEN nama_umkm LIKE ? THEN 2
                            WHEN deskripsi LIKE ? THEN 3
                            ELSE 4
                        END
                    ", [
                        $searchTerm . '%',
                        '%' . $searchTerm . '%',
                        '%' . $searchTerm . '%'
                    ]);
                }
                $query->orderBy('created_at', 'desc');
                break;
        }

        $umkms = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => UmkmResource::collection($umkms),
            'meta' => [
                'total' => $umkms->total(),
                'per_page' => $umkms->perPage(),
                'current_page' => $umkms->currentPage(),
                'last_page' => $umkms->lastPage(),
            ]
        ]);
    }

    /**
     * Get categories for search filter
     */
    public function getCategories(Request $request)
    {
        $query = Category::query();

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where('nama_kategori', 'like', '%' . $searchTerm . '%');
        }

        $categories = $query->withCount(['products' => function($q) {
            $q->where('is_active', true);
        }])
        ->orderBy('nama_kategori')
        ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Get kecamatans for search filter
     */
    public function getKecamatans(Request $request)
    {
        $query = Kecamatan::query();

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where('nama_kecamatan', 'like', '%' . $searchTerm . '%');
        }

        $kecamatans = $query->withCount(['umkms' => function($q) {
            $q->where('is_approved', true);
        }])
        ->orderBy('nama_kecamatan')
        ->get();

        return KecamatanResource::collection($kecamatans);
    }

    public function productCount(Request $request)
    {
        $query = Product::where('is_active', true);

        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            $query->where('nama_produk', 'like', '%' . $searchTerm . '%');
        }

        return response()->json([
            'success' => true,
            'count' => $query->count()
        ]);
    }

    public function umkmCount(Request $request)
    {
        $query = Umkm::query();

        if ($request->has('q') && !empty($request->q)) {
            $searchTerm = $request->q;
            $query->where('nama_umkm', 'like', '%' . $searchTerm . '%');
        }

        return response()->json([
            'success' => true,
            'count' => $query->count()
        ]);
    }
}
