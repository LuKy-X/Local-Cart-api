<?php

namespace App\Http\Controllers\Api;

use App\Models\Umkm;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\OngkirService;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Requests\StoreOrderRequest;

class OrderController extends Controller
{
    protected $ongkirService;

    public function __construct(OngkirService $ongkirService)
    {
        $this->ongkirService = $ongkirService;
    }

    public function index()
    {
        $orders = Order::with(['umkm.user', 'customer.user', 'shipper', 'orderItems.product'])
                      ->latest()
                      ->paginate(12);

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        $user = $request->user();
        $customer = $user->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        if (!$customer->kecamatan_id) {
            return response()->json([
                'message' => 'Alamat kecamatan belum diatur. Silakan update profil customer terlebih dahulu.'
            ], 422);
        }

        // Validasi bahwa semua produk dari UMKM yang sama
        $umkmIds = [];
        foreach ($request->order_items as $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $umkmIds[] = $product->umkm_id;
            }
        }

        $uniqueUmkmIds = array_unique($umkmIds);

        if (count($uniqueUmkmIds) > 1) {
            return response()->json([
                'message' => 'Order hanya bisa berisi produk dari satu UMKM'
            ], 422);
        }

        $umkmId = $uniqueUmkmIds[0];
        $umkm = Umkm::find($umkmId);

        if (!$umkm || !$umkm->kecamatan_id) {
            return response()->json([
                'message' => 'UMKM belum mengatur kecamatan. Silakan hubungi UMKM terlebih dahulu.'
            ], 422);
        }

        $ongkirInfo = $this->ongkirService->calculateOngkirFromOrder($umkmId, $customer->id);

        $kodeOrder = 'ORD' . date('Ymd') . Str::upper(Str::random(6));
        $totalHarga = 0;
        $orderItems = [];

        foreach ($request->order_items as $item) {
            $product = Product::findOrFail($item['product_id']);

            if ($product->umkm_id !== $umkmId) {
                return response()->json([
                    'message' => 'Semua produk harus dari UMKM yang sama'
                ], 422);
            }

            if ($product->stok < $item['quantity']) {
                return response()->json([
                    'message' => "Stok produk {$product->nama_produk} tidak mencukupi"
                ], 422);
            }

            $subtotal = $product->harga * $item['quantity'];
            $totalHarga += $subtotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'harga_satuan' => $product->harga,
                'subtotal' => $subtotal,
            ];
        }

        $ongkir = $ongkirInfo['tarif'];
        $grandTotal = $totalHarga + $ongkir;

        $order = Order::create([
            'umkm_id' => $umkmId,
            'customer_id' => $customer->id,
            'shipper_id' => null, // Boleh null
            'kode_order' => $kodeOrder,
            'nomor_resi' => null, // Boleh null
            'alamat_pengiriman' => $request->alamat_pengiriman,
            'total_harga' => $totalHarga,
            'ongkir' => $ongkir,
            'grand_total' => $grandTotal,
            'status' => 'pending',
            'estimasi_pengiriman' => $ongkirInfo['estimasi_hari'],
            'jarak_km' => $ongkirInfo['jarak_km'],
        ]);

        foreach ($orderItems as $item) {
            OrderItem::create(array_merge($item, ['order_id' => $order->id]));
            $product = Product::find($item['product_id']);
            $product->decrement('stok', $item['quantity']);
        }

        return new OrderResource($order->load(['umkm', 'customer', 'shipper', 'orderItems.product']));
    }

    public function checkOngkir(Request $request)
    {
        $request->validate([
            'umkm_id' => 'required|exists:umkms,id',
            'customer_id' => 'required|exists:customers,id',
        ]);

        $ongkirInfo = $this->ongkirService->calculateOngkirFromOrder(
            $request->umkm_id,
            $request->customer_id
        );

        return response()->json([
            'success' => true,
            'ongkir' => $ongkirInfo['tarif'],
            'estimasi_hari' => $ongkirInfo['estimasi_hari'],
            'keterangan' => $ongkirInfo['keterangan']
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        if ($user->isCustomer() && $order->customer_id !== $user->customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->isUmkm() && $order->umkm_id !== $user->umkm->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new OrderResource($order->load(['umkm.user', 'customer.user', 'shipper', 'orderItems.product']));
    }

    public function myOrders(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer profile not found'], 404);
        }

        $orders = $customer->orders()
                          ->with(['umkm.user', 'shipper', 'orderItems.product'])
                          ->withCount('orderItems')
                          ->latest()
                          ->paginate(12);

        return OrderResource::collection($orders);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled'
        ]);

        if ($request->user()->isUmkm() && $order->umkm_id !== $request->user()->umkm->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->update(['status' => $request->status]);

        return new OrderResource($order->load(['umkm.user', 'customer.user', 'shipper', 'orderItems.product']));
    }

    public function statistics()
    {
        $totalPending = Order::where('status', 'pending')->count();
        $totalProcessing = Order::where('status', 'processing')->count();
        $totalShipped = Order::where('status', 'shipped')->count();
        $totalDelivered = Order::where('status', 'delivered')->count();

        return response()->json([
            'total_pending' => $totalPending,
            'total_processing' => $totalProcessing,
            'total_shipped' => $totalShipped,
            'total_delivered' => $totalDelivered,
        ]);
    }

    public function adminList(Request $request)
    {
        $query = Order::with(['umkm', 'customer', 'customer.user','shipper', 'orderItems'])
            ->withCount('orderItems');

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('kode_order', 'like', '%' . $searchTerm . '%')
                  ->orWhere('nomor_resi', 'like', '%' . $searchTerm . '%')
                  ->orWhere('alamat_pengiriman', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('umkm', function($q2) use ($searchTerm) {
                      $q2->where('nama_umkm', 'like', '%' . $searchTerm . '%');
                  })
                  ->orWhereHas('customer', function($q2) use ($searchTerm) {
                      $q2->where('nama', 'like', '%' . $searchTerm . '%');
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

        $allowedSortFields = ['created_at', 'updated_at', 'grand_total', 'status'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $orders = $query->paginate($perPage);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ]
        ]);
    }

    public function orderDetail($id)
    {
        $order = Order::with([
            'umkm.user',
            'customer.user',
            'shipper',
            'orderItems.product' => function($query) {
                $query->with(['category', 'umkm']);
            }
        ])->withCount('orderItems')->findOrFail($id);

        return new OrderResource($order);
    }

    public function updateOrderStatus($id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled'
        ]);

        try {
            $order = Order::find($id);

            $order->status = $request->status;
            $order->updated_at = now();

            if ($order->save()) {

                $order->load(['umkm.user', 'customer.user', 'shipper', 'orderItems.product']);

                return new OrderResource($order);
            } else {
                return response()->json([
                    'message' => 'Gagal menyimpan status order'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengupdate status order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelOrder(Request $request, Order $order)
    {
        $user = $request->user();

        // Check if user is the customer who made the order
        if ($user->isCustomer() && $order->customer_id !== $user->customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if order can be cancelled (only pending orders)
        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Pesanan tidak dapat dibatalkan karena sudah diproses'
            ], 422);
        }

        $order->status = 'cancelled';
        $order->save();

        // Restore product stock
        foreach ($order->orderItems as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->increment('stok', $item->quantity);
            }
        }

        return new OrderResource($order->load(['umkm', 'customer', 'shipper', 'orderItems.product']));
    }
}
