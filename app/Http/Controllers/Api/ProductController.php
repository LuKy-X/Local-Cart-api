<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductView;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductCollection;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);

        $query = Product::with(['umkm', 'category'])
            ->active();

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

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nama_produk', 'like', "%{$search}%");
        }

        // Sorting
        switch ($request->get('sort_by', 'newest')) {
            case 'price_low':
                $query->orderBy('harga', 'asc');
                break;
            case 'price_high':
                $query->orderBy('harga', 'desc');
                break;
            case 'rating':
                $query->orderByRaw('(SELECT AVG(rating) FROM ratings WHERE product_id = products.id) DESC');
                break;
            case 'popular':
                $query->orderByRaw('(SELECT COUNT(*) FROM order_items WHERE product_id = products.id) DESC');
                break;
            default: // newest
                $query->orderBy('created_at', 'desc');
        }

        $products = $query->paginate($perPage);

        return new ProductCollection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $user = $request->user();

        if (!$user->isUmkm()) {
            return response()->json(['message' => 'Hanya UMKM yang bisa membuat produk'], 403);
        }

        $umkm = $user->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('product-images', 'public');
            $data['foto'] = $path;
        }

        $product = Product::create(array_merge($data, [
            'umkm_id' => $umkm->id
        ]));

        return new ProductResource($product->load(['umkm', 'category']));
    }

    public function show(Request $request, Product $product)
    {
        ProductView::create([
            'product_id' => $product->id,
            'customer_id' => $request->user() && $request->user()->isCustomer() ? $request->user()->customer->id : null,
            'ip_address' => $request->ip(),
        ]);

        return new ProductResource($product->load(['umkm.user', 'category', 'ratings.customer.user']));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        // dd($request->all(), $request->file('foto'));

        $user = $request->user();

        if ($user->isUmkm() && $product->umkm_id !== $user->umkm->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();

        if ($request->hasFile('foto')) {
            if ($product->foto) {
                Storage::disk('public')->delete($product->foto);
            }

            $path = $request->file('foto')->store('product-images', 'public');
            $data['foto'] = $path;
        }

        $product->update($data);

        return new ProductResource($product->load(['umkm', 'category']));
    }

    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();

        if ($user->isUmkm() && $product->umkm_id !== $user->umkm->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($product->foto) {
            Storage::disk('public')->delete($product->foto);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    // ------------------Admin---------------

    public function statistics()
    {
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $inactiveProducts = Product::where('is_active', false)->count();
        $outOfStockProducts = Product::where('stok', 0)->count();
        $productsWithRatings = Product::has('ratings')->count();
        $totalCategories = Category::count();

        return response()->json([
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'inactive_products' => $inactiveProducts,
            'out_of_stock_products' => $outOfStockProducts,
            'products_with_ratings' => $productsWithRatings,
            'total_categories' => $totalCategories,
        ]);
    }

    public function adminList(Request $request)
    {

        $query = Product::with(['umkm', 'category', 'umkm.user', 'umkm.kecamatan', 'ratings'])
                        ->withCount(['ratings', 'views', 'orderItems']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('nama_produk', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('umkm', function($umkmQuery) use ($searchTerm) {
                      $umkmQuery->where('nama_umkm', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // Category filter
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        // Status filter
        if ($request->has('is_active')) {
            $isActive = $request->is_active;
            // Hanya apply filter jika bukan null dan bukan string kosong
            if ($isActive !== null && $isActive !== '') {
                $query->where('is_active', $isActive);
                // \Log::info('After status filter:', [
                //     'count' => $query->count(),
                //     'is_active_value' => $isActive,
                //     'is_active_type' => gettype($isActive)
                // ]);
            }
        }

        // Stock filter
        if ($request->has('stock_status') && !empty($request->stock_status)) {
            if ($request->stock_status === 'in_stock') {
                $query->where('stok', '>', 0);
            } elseif ($request->stock_status === 'out_of_stock') {
                $query->where('stok', 0);
            }
        }

        // Sort options
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSortFields = ['nama_produk', 'harga', 'stok', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $products = $query->paginate($perPage);

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }

    public function toggleStatus(Request $request, Product $product)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'admin') {
            $product->is_active = !$product->is_active;
            $product->save();
        } else if ($user->role === 'umkm') {
            $umkm = $user->umkm;
            if (!$umkm || $product->umkm_id !== $umkm->id) {
                return response()->json(['message' => 'You are not allowed to toggle this product status.'], 403);
            } else {
                $product->is_active = !$product->is_active;
                $product->save();
            }
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'message' => 'Status produk berhasil diubah',
            'is_active' => $product->is_active
        ]);
    }

    public function categories()
    {
        $categories = Category::withCount('products')->get();
        return CategoryResource::collection($categories);
    }


    // ======================= public =======================
    public function featured(Request $request)
    {
        $perPage = $request->get('per_page', 20);

        // Gunakan subquery untuk mendapatkan produk dengan rating tinggi
        $products = Product::with(['umkm', 'category'])
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
            ->paginate($perPage);

        return new ProductCollection($products);
    }

    /**
     * Get new products
     */
    public function new(Request $request)
    {
        $perPage = $request->get('per_page', 20);

        $products = Product::with(['umkm', 'category'])
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return new ProductCollection($products);
    }

    /**
     * Get popular products (most sales)
     */
    public function popular(Request $request)
    {
        $perPage = $request->get('per_page', 20);

        // Hitung penjualan dalam 30 hari terakhir
        $products = Product::with(['umkm', 'category'])
            ->active()
            ->withCount(['orderItems as recent_sales' => function($query) {
                $query->whereHas('order', function($q) {
                    $q->where('created_at', '>=', now()->subDays(30));
                });
            }])
            ->orderBy('recent_sales', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return new ProductCollection($products);
    }

    /**
     * Get most viewed products
     */
    public function mostViewed(Request $request)
    {
        $perPage = $request->get('per_page', 20);

        $products = Product::with(['umkm', 'category'])
            ->active()
            ->withCount('views')
            ->orderBy('views_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return new ProductCollection($products);
    }


    /**
     * Get products by UMKM
     */
    public function byUmkm(Request $request, $umkmId)
    {
        $perPage = $request->get('per_page', 20);

        $products = Product::with(['umkm', 'category'])
            ->where('umkm_id', $umkmId)
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return new ProductCollection($products);
    }

    /**
     * Get products by category
     */
    public function byCategory(Request $request, $categoryId)
    {
        $perPage = $request->get('per_page', 20);

        $products = Product::with(['umkm', 'category'])
            ->where('category_id', $categoryId)
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return new ProductCollection($products);
    }

    /**
     * Track product view
     */
    private function trackView($productId)
    {
        $product = Product::find($productId);

        if ($product) {
            $viewData = [
                'product_id' => $productId,
                'ip_address' => request()->ip(),
            ];

            $user = request()->user();
            if ($user && $user->isCustomer()) {
                $viewData['customer_id'] = $user->customer->id ?? null;
            }

            $product->views()->create($viewData);
        }
    }

    public function showWithDetails($id)
    {
        $product = Product::with([
            'umkm.kecamatan',
            'category',
            'ratings',
            'ratings.customer.user'  // Include customer and user data for ratings
        ])
        ->active()
        ->findOrFail($id);

        // Increment view count or create view record
        $this->trackView($id);

        $product->loadCount(['orderItems as total_sold' => function($query) {
            $query->whereHas('order', function($q) {
                $q->where('status', 'delivered');
            });
        }]);

        return new ProductResource($product);
    }

    /**
     * Get recommended products (from same UMKM)
     */
    public function recommendedProducts(Request $request, $id)
    {
        $perPage = $request->get('per_page', 5);

        // Get current product
        $product = Product::findOrFail($id);

        // Get products from same UMKM
        $recommended = Product::with(['umkm', 'category'])
            ->where('umkm_id', $product->umkm_id)
            ->where('id', '!=', $id)
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return new ProductCollection($recommended);
    }

    /**
     * Get product reviews with pagination
     */
    public function reviews(Request $request, $id)
    {
        $perPage = $request->get('per_page', 10);

        $product = Product::findOrFail($id);

        $reviews = $product->ratings()
            ->with(['customer.user', 'order'])
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ]
        ]);
    }


    public function productCount()
    {
        $productCount = Product::where('is_active', 1)->count();

        return response()->json(['product_count' => $productCount]);
    }
}
