<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')
                        ->latest()
                        ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create($request->validated());

        return new CategoryResource($category);
    }

    public function show(Category $category)
    {
        return new CategoryResource($category->load(['products.umkm']));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with existing products'
            ], 422);

        }

        $category->delete();

        return response()->json(['message' => 'Category berhasil dihapus']);
    }


    public function statistics()
    {
        $totalCategories = Category::count();
        $categoriesWithProducts = Category::has('products')->count();
        $totalProducts = Product::count();

        // Rata-rata produk per kategori
        $averageProductsPerCategory = Category::has('products')
            ->withCount('products')
            ->get()
            ->avg('products_count') ?? 0;

        return response()->json([
            'total_categories' => $totalCategories,
            'categories_with_products' => $categoriesWithProducts,
            'total_products' => $totalProducts,
            'average_products_per_category' => round($averageProductsPerCategory, 1),
        ]);
    }

    public function adminList(Request $request)
    {
        $query = Category::withCount('products');

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('nama_kategori', 'like', '%' . $searchTerm . '%')
                  ->orWhere('deskripsi', 'like', '%' . $searchTerm . '%');
            });
        }

        // Sort options
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSortFields = ['nama_kategori', 'products_count', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $categories = $query->paginate($perPage);

        return response()->json([
            'data' => CategoryResource::collection($categories),
            'meta' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
            ]
        ]);
    }

    public function categoryDetail($id)
    {
        $category = Category::with(['products' => function($query) {
            $query->with(['umkm', 'ratings'])
                  ->withCount(['ratings', 'views', 'orderItems'])
                  ->orderBy('created_at', 'desc')
                  ->limit(10); // Batasi produk yang ditampilkan di modal
        }])->withCount('products')->findOrFail($id);

        return new CategoryResource($category);
    }

    public function byUmkm($umkmId)
    {
        $categories = Category::whereHas('products', function ($query) use ($umkmId) {
            $query->where('umkm_id', $umkmId)->where('is_active', true);
        })
        ->withCount(['products' => function ($query) use ($umkmId) {
            $query->where('umkm_id', $umkmId)->where('is_active', true);
        }])
        ->get();

        return response()->json($categories);
    }
}
