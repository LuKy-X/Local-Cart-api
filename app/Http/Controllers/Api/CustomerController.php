<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\CustomerResource;

class CustomerController extends Controller
{
    public function myProfile(Request $request)
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json(['message' => 'Profile customer tidak ditemukan'], 404);
        }

        return new CustomerResource($customer->load(['user'])->loadCount(['orders', 'carts', 'ratings']));
    }

    public function updateProfile(Request $request)
    {
        $customer = $request->user()->customer;
        if (!$customer) {
            return response()->json(['message' => 'Profile customer tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'alamat' => 'sometimes|string|max:500',
            'telepon' => 'sometimes|string|max:15',
            'kecamatan_id' => 'sometimes|exists:kecamatans,id',
            'foto' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle foto upload
        if ($request->hasFile('foto')) {
            // Delete old foto if exists
            if ($customer->foto ) {
                Storage::disk('public')->delete($customer->foto);
            }

            $path = $request->file('foto')->store('customer_images', 'public');
            $validated['foto'] = $path;
        }

        $customer->update($validated);

        if ($request->has('name')) {
            $request->user()->update(['name' => $request->name]);
        }

        return new CustomerResource($customer->fresh()->load(['user', 'kecamatan'])->loadCount(['orders', 'carts', 'ratings']));
    }


    //------------------Admin--------------------
    public function index(Request $request)
    {
        $query = Customer::with(['user', 'kecamatan'])
                        ->withCount(['orders', 'ratings']);

        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('nama_customer', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'like', '%' . $searchTerm . '%')
                               ->orWhere('email', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        if ($request->has('kecamatan_id') && !empty($request->kecamatan_id)) {
            $query->where('kecamatan_id', $request->kecamatan_id);
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSortFields = ['nama_customer', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 10);
        $customers = $query->paginate($perPage);

        return response()->json([
            'data' => CustomerResource::collection($customers),
            'meta' => [
                'total' => $customers->total(),
                'per_page' => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
            ]
        ]);
    }

    public function statistics()
    {
        $totalCustomers = Customer::count();
        $totalWithOrders = Customer::has('orders')->count();
        $totalWithRatings = Customer::has('ratings')->count();
        $newCustomersThisMonth = Customer::whereMonth('created_at', now()->month)
                                    ->whereYear('created_at', now()->year)
                                    ->count();

        return response()->json([
            'total_customers' => $totalCustomers,
            'total_with_orders' => $totalWithOrders,
            'total_with_ratings' => $totalWithRatings,
            'new_customers_this_month' => $newCustomersThisMonth,
        ]);
    }
}
