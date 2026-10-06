<?php

namespace App\Http\Controllers\Api;

use App\Models\Umkm;
use App\Models\Order;
use App\Models\Rating;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\UmkmResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\UpdateUmkmRequest;

class UmkmController extends Controller
{
    public function index()
    {
        $umkms = Umkm::with(['user', 'products'])
                    ->where('is_approved', true)
                    ->latest()
                    ->paginate(10);

        return UmkmResource::collection($umkms);
    }

    public function show(Umkm $umkm)
    {

        $umkm->load([
            'user',
            'kecamatan',
            'products.ratings',
            'products.orderItems'
        ]);

        return new UmkmResource($umkm);
    }

    public function myUmkm(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'Umkm tidak ditemukan'], 404);
        }

        // Load relationships
        $umkm->load(['user', 'kecamatan']);
        $umkm->products_count = $umkm->products()->count();
        $umkm->orders_count = $umkm->orders()->count();

        return new UmkmResource($umkm);
    }

    public function updateUmkm(UpdateUmkmRequest $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'Umkm tidak ditemukan'], 404);
        }

        $data = $request->validated();

        if ($request->hasFile('foto_logo')) {
            if ($umkm->foto_logo) {
                Storage::disk('public')->delete($umkm->foto_logo);
            }

            $path = $request->file('foto_logo')->store('umkm_logos', 'public');
            $data['foto_logo'] = $path;
        }

        $umkm->update($data);

        $umkm->load(['user', 'kecamatan']);
        $umkm->products_count = $umkm->products()->count();
        $umkm->orders_count = $umkm->orders()->count();

        return new UmkmResource($umkm->load(['user', 'kecamatan']));
    }

    public function myProducts(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $query = $umkm->products()->with(['category', 'ratings'])
                                    ->withCount(['ratings', 'views', 'orderItems']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('nama_produk', 'like', '%' . $searchTerm . '%');
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

    public function orderStatistics(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $totalPending = $umkm->orders()->where('status', 'pending')->count();
        $totalProcessing = $umkm->orders()->where('status', 'processing')->count();
        $totalShipped = $umkm->orders()->where('status', 'shipped')->count();
        $totalDelivered = $umkm->orders()->where('status', 'delivered')->count();
        $totalCancelled = $umkm->orders()->where('status', 'cancelled')->count();

        return response()->json([
            'total_pending' => $totalPending,
            'total_processing' => $totalProcessing,
            'total_shipped' => $totalShipped,
            'total_delivered' => $totalDelivered,
            'total_cancelled' => $totalCancelled,
        ]);
    }

    public function umkmOrderDetail(Request $request, Order $order)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm || $order->umkm_id !== $umkm->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->load([
            'customer.user',
            'shipper',
            'orderItems.product.category',
            'orderItems.product.umkm'
        ]);

        return new OrderResource($order);
    }

    public function updateOrderShipper(Request $request, Order $order)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm || $order->umkm_id !== $umkm->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'shipper_id' => 'required|exists:shippers,id'
        ]);

        // Update shipper dan generate nomor resi
        $order->updateShipper($request->shipper_id);

        return new OrderResource($order->load(['customer.user', 'shipper', 'orderItems.product']));
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm || $order->umkm_id !== $umkm->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled'
        ]);

        // Validasi khusus untuk status shipped
        if ($request->status === 'shipped') {
            if (!$order->canUpdateToShipped()) {
                return response()->json([
                    'message' => 'Shipper harus dipilih sebelum mengubah status menjadi shipped.',
                    'requires_shipper' => true
                ], 422);
            }

            // Pastikan nomor resi sudah ada
            if (!$order->nomor_resi) {
                $order->nomor_resi = $order->generateNomorResi();
                $order->save();
            }
        }

        $order->update(['status' => $request->status]);

        return new OrderResource($order->load(['customer.user', 'shipper', 'orderItems.product']));
    }

    public function myOrders(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $query = $umkm->orders()
                    ->with(['customer.user', 'shipper', 'orderItems.product.category'])
                    ->withCount(['orderItems']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('kode_order', 'like', '%' . $searchTerm . '%')
                ->orWhere('nomor_resi', 'like', '%' . $searchTerm . '%')
                ->orWhereHas('customer', function($customerQuery) use ($searchTerm) {
                    $customerQuery->where('nama_customer', 'like', '%' . $searchTerm . '%')
                                ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                                    $userQuery->where('email', 'like', '%' . $searchTerm . '%');
                                });
                });
            });
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
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

        $allowedSortFields = ['created_at', 'grand_total', 'status'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $orders = $query->paginate($perPage);

        return OrderResource::collection($orders);
    }

    public function analytics(Request $request)
    {
        $umkm = $request->user()->umkm;

        if (!$umkm) {
            return response()->json(['message' => 'UMKM tidak ditemukan'], 404);
        }

        $totalProducts = $umkm->products()->count();
        $totalOrders = $umkm->orders()->count();
        $totalRevenue = $umkm->orders()->where('status', 'delivered')->sum('grand_total');
        $pendingOrders = $umkm->orders()->where('status', 'pending')->count();

        return response()->json([
            'total_products' => $totalProducts,
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'pending_orders' => $pendingOrders,
        ]);
    }


    // ---------------------Admin----------------------
    public function pendingUmkms()
    {
        $umkms = Umkm::with(['user'])
                    ->where('is_approved', false)
                    ->latest()
                    ->paginate(12);

        return UmkmResource::collection($umkms);
    }

    public function approve(Umkm $umkm)
    {
        $umkm->update(['is_approved' => true]);

        return response()->json(['message' => 'UMKM approved successfully']);
    }

    public function reject(Umkm $umkm)
    {
        $umkm->update(['is_approved' => false]);

        return response()->json(['message' => 'UMKM rejected successfully']);
    }

    public function adminAnalytics()
    {
        $totalUmkms = Umkm::count();
        $totalCustomers = Customer::count();
        $totalProducts = Product::count();
        $totalOrders = Order::count();
        $totalRevenue = Order::where('status', 'delivered')->sum('grand_total');

        return response()->json([
            'total_umkms' => $totalUmkms,
            'total_customers' => $totalCustomers,
            'total_products' => $totalProducts,
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
        ]);
    }

    public function adminUmkm(Request $request)
    {
        $query = Umkm::with(['user', 'kecamatan'])
                    ->withCount(['products', 'orders']);

        // Search by nama_umkm or owner name
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('nama_umkm', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'like', '%' . $searchTerm . '%')
                               ->orWhere('email', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // Filter by status approval
        if ($request->has('status') && !empty($request->status)) {
            switch ($request->status) {
                case 'pending':
                    $query->where('is_approved', false);
                    break;
                case 'approved':
                    $query->where('is_approved', true);
                    break;
            }
        }

        // Filter by kecamatan
        if ($request->has('kecamatan_id') && !empty($request->kecamatan_id)) {
            $query->where('kecamatan_id', $request->kecamatan_id);
        }

        // Sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSortFields = ['nama_umkm', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Get paginated results
        $perPage = $request->get('per_page', 10);
        $umkms = $query->paginate($perPage);

        return response()->json([
            'data' => UmkmResource::collection($umkms),
            'meta' => [
                'total' => $umkms->total(),
                'per_page' => $umkms->perPage(),
                'current_page' => $umkms->currentPage(),
                'last_page' => $umkms->lastPage(),
            ]
        ]);
    }

    public function umkmStatistics()
    {
        $totalUmkms = Umkm::count();
        $totalPending = Umkm::where('is_approved', false)->count();
        $totalApproved = Umkm::where('is_approved', true)->count();

        return response()->json([
            'total_umkms' => $totalUmkms,
            'total_pending' => $totalPending,
            'total_approved' => $totalApproved,
        ]);
    }


    public function reviews(Umkm $umkm, Request $request)
    {
        $query = Rating::whereHas('product', function ($query) use ($umkm) {
            $query->where('umkm_id', $umkm->id);
        })
        ->with(['customer.user', 'product', 'order'])
        ->where('is_approved', true);

        // Filter by star rating
        if ($request->has('star') && $request->star) {
            $query->where('rating', $request->star);
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'newest');
        switch ($sortBy) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'highest':
                $query->orderBy('rating', 'desc');
                break;
            case 'lowest':
                $query->orderBy('rating', 'asc');
                break;
            case 'helpful':
                $query->orderBy('helpful_count', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 5);
        $reviews = $query->paginate($perPage);

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'total' => $reviews->total(),
                'per_page' => $reviews->perPage(),
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
            ]
        ]);
    }


    public function umkmCount()
    {
        $umkmCount = Umkm::where('is_approved', true)->count();

        return response()->json(['umkm_count' => $umkmCount]);
    }
}
