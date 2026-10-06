<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Rating;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\RatingResource;
use App\Http\Requests\RateProductRequest;

class RatingController extends Controller
{
    public function index()
    {
        $ratings = Rating::with(['customer.user', 'product.umkm', 'order'])
                        ->latest()
                        ->paginate(12);

        return RatingResource::collection($ratings);
    }

    public function rateOrder(RateProductRequest $request, Order $order)
    {
        $user = $request->user();
        $customer = $user->customer;

        if (!$customer || $order->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'delivered') {
            return response()->json([
                'message' => 'Hanya order yang sudah delivered yang bisa dirating'
            ], 422);
        }

        $product = Product::findOrFail($request->product_id);

        $orderItem = $order->orderItems()
                          ->where('product_id', $request->product_id)
                          ->first();

        if (!$orderItem) {
            return response()->json([
                'message' => 'Product tidak ditemukan dalam order ini'
            ], 422);
        }

        $existingRating = Rating::where('customer_id', $customer->id)
                               ->where('product_id', $request->product_id)
                               ->where('order_id', $order->id)
                               ->first();

        if ($existingRating) {
            return response()->json([
                'message' => 'Anda sudah memberikan rating untuk produk ini dalam order ini'
            ], 422);
        }

        $rating = Rating::create([
            'customer_id' => $customer->id,
            'product_id' => $request->product_id,
            'order_id' => $order->id,
            'rating' => $request->rating,
            'review' => $request->review,
        ]);

        return new RatingResource($rating->load(['customer.user', 'product.umkm', 'order']));
    }

    public function myRatings(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $ratings = $customer->ratings()
                           ->with(['product.umkm', 'order'])
                           ->latest()
                           ->paginate(12);

        return RatingResource::collection($ratings);
    }

    public function productRatings(Product $product)
    {
        $ratings = $product->ratings()
                          ->with(['customer.user'])
                          ->where('is_approved', true)
                          ->latest()
                          ->paginate(12);

        return RatingResource::collection($ratings);
    }

    public function updateApproval(Request $request, Rating $rating)
    {
        $request->validate([
            'is_approved' => 'required|boolean'
        ]);

        $rating->update(['is_approved' => $request->is_approved]);

        return new RatingResource($rating->load(['customer.user', 'product.umkm', 'order']));
    }

    public function statistics()
    {
        $totalRatings = Rating::count();
        $averageRating = Rating::avg('rating') ?? 0;
        $approvedRatings = Rating::where('is_approved', true)->count();
        $pendingRatings = Rating::where('is_approved', false)->count();

        // Distribusi rating
        $ratingDistribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $ratingDistribution[$i] = Rating::where('rating', $i)->count();
        }

        return response()->json([
            'total_ratings' => $totalRatings,
            'average_rating' => round($averageRating, 2),
            'approved_ratings' => $approvedRatings,
            'pending_ratings' => $pendingRatings,
            'rating_distribution' => $ratingDistribution,
        ]);
    }

    public function adminList(Request $request)
    {
        $query = Rating::with(['customer.user', 'product.umkm', 'order']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('review', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('customer', function($q2) use ($searchTerm) {
                      $q2->where('nama', 'like', '%' . $searchTerm . '%');
                  })
                  ->orWhereHas('product', function($q2) use ($searchTerm) {
                      $q2->where('nama_produk', 'like', '%' . $searchTerm . '%');
                  })
                  ->orWhereHas('order', function($q2) use ($searchTerm) {
                      $q2->where('kode_order', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // Approval status filter
        if ($request->has('is_approved')) {
            $isApprovedParam = $request->is_approved;

            // Hanya apply filter jika bukan null dan bukan empty string
            if (!is_null($isApprovedParam) && $isApprovedParam !== '') {
                // Convert ke boolean dengan aman
                if (is_string($isApprovedParam)) {
                    if ($isApprovedParam === 'true' || $isApprovedParam === '1') {
                        $query->where('is_approved', true);
                    } elseif ($isApprovedParam === 'false' || $isApprovedParam === '0') {
                        $query->where('is_approved', false);
                    }
                } elseif (is_bool($isApprovedParam)) {
                    // Jika sudah boolean langsung
                    $query->where('is_approved', $isApprovedParam);
                }
            }
            // Jika null atau empty string, jangan apply filter (tampilkan semua)
        }

        // Rating filter
        if ($request->has('rating') && !empty($request->rating)) {
            $query->where('rating', $request->rating);
        }

        // Date range filter
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Sort options
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSortFields = ['created_at', 'updated_at', 'rating'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $ratings = $query->paginate($perPage);

        return response()->json([
            'data' => RatingResource::collection($ratings),
            'meta' => [
                'total' => $ratings->total(),
                'per_page' => $ratings->perPage(),
                'current_page' => $ratings->currentPage(),
                'last_page' => $ratings->lastPage(),
            ]
        ]);
    }

    public function ratingDetail($id)
    {
        $rating = Rating::with([
            'customer.user',
            'product.umkm',
            'order' => function($query) {
                $query->with(['umkm', 'customer', 'orderItems.product']);
            }
        ])->findOrFail($id);

        return new RatingResource($rating);
    }

    public function deleteRating($id)
    {
        $rating = Rating::findOrFail($id);
        $rating->delete();

        return response()->json([
            'message' => 'Rating berhasil dihapus'
        ]);
    }
}
