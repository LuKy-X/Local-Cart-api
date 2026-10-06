<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Shipper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipperResource;
use App\Http\Requests\UpdateShipperRequest;

class ShipperController extends Controller
{
    public function index()
    {
        $shippers = Shipper::latest()->get();

        return ShipperResource::collection($shippers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'shipper_name' => 'required|string|max:255|unique:shippers',
        ]);

        $shipper = Shipper::create($validated);

        return new ShipperResource($shipper);
    }

    public function show(Shipper $shipper): ShipperResource
    {
        return new ShipperResource($shipper->load(['orders']));
    }

    public function update(UpdateShipperRequest $request, Shipper $shipper)
    {
        $shipper->update($request->validated());

        return new ShipperResource($shipper);
    }

    public function destroy(Shipper $shipper)
    {
        if ($shipper->orders()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete shipper with existing orders'
            ], 422);
        }

        $shipper->delete();

        return response()->json(['message' => 'Shipper deleted successfully']);
    }

    public function statistics()
    {
        $totalShippers = Shipper::count();
        $shippersWithOrders = Shipper::has('orders')->count();
        $shippersWithoutOrders = Shipper::doesntHave('orders')->count();
        $totalOrders = Order::count();

        // Shipper dengan order terbanyak
        $mostOrdersShipper = Shipper::withCount('orders')
            ->orderBy('orders_count', 'desc')
            ->first();

        // Rata-rata order per shipper
        $averageOrdersPerShipper = Shipper::has('orders')
            ->withCount('orders')
            ->get()
            ->avg('orders_count') ?? 0;

        return response()->json([
            'total_shippers' => $totalShippers,
            'shippers_with_orders' => $shippersWithOrders,
            'shippers_without_orders' => $shippersWithoutOrders,
            'total_orders' => $totalOrders,
            'most_orders_shipper' => $mostOrdersShipper ? [
                'name' => $mostOrdersShipper->shipper_name,
                'count' => $mostOrdersShipper->orders_count
            ] : null,
            'average_orders_per_shipper' => round($averageOrdersPerShipper, 1),
        ]);
    }

    public function adminList(Request $request)
    {
        $query = Shipper::withCount('orders');

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('shipper_name', 'like', '%' . $searchTerm . '%');
        }

        // Sort options
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSortFields = ['shipper_name', 'orders_count', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $shippers = $query->paginate($perPage);

        return response()->json([
            'data' => ShipperResource::collection($shippers),
            'meta' => [
                'total' => $shippers->total(),
                'per_page' => $shippers->perPage(),
                'current_page' => $shippers->currentPage(),
                'last_page' => $shippers->lastPage(),
            ]
        ]);
    }

    public function shipperDetail($id)
    {
        $shipper = Shipper::with(['orders' => function($query) {
            $query->with(['umkm', 'customer'])
                  ->orderBy('created_at', 'desc')
                  ->limit(10); // Batasi order yang ditampilkan di modal
        }])->withCount('orders')->findOrFail($id);

        return new ShipperResource($shipper);
    }
}
