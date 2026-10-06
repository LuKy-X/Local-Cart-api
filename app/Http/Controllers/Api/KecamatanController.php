<?php

namespace App\Http\Controllers\Api;

use App\Models\Kecamatan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\KecamatanResource;
use App\Http\Requests\StoreKecamatanRequest;
use App\Http\Requests\UpdateKecamatanRequest;

class KecamatanController extends Controller
{
    public function index()
    {
        $kecamatans = Kecamatan::withCount(['umkms', 'customers'])
                        ->orderBy('nama_kecamatan')
                        ->get();
        return KecamatanResource::collection($kecamatans);
    }

    public function show(Kecamatan $kecamatan)
    {
        return new KecamatanResource($kecamatan);
    }

    public function store(StoreKecamatanRequest $request)
    {
        $kecamatan = Kecamatan::create($request->validated());
        return new KecamatanResource($kecamatan);
    }

    public function update(UpdateKecamatanRequest $request, Kecamatan $kecamatan)
    {
        $kecamatan->update($request->validated());
        return new KecamatanResource($kecamatan);
    }

    public function destroy(Kecamatan $kecamatan)
    {
        if ($kecamatan->umkms()->count() > 0) {
            return response()->json([
                'message' => 'Tidak dapat menghapus kecamatan yang masih memiliki UMKM'
            ], 422);
        }

        if ($kecamatan->customers()->count() > 0) {
            return response()->json([
                'message' => 'Tidak dapat menghapus kecamatan yang masih memiliki customer'
            ], 422);
        }

        $kecamatan->delete();

        return response()->json([
            'message' => 'Kecamatan berhasil dihapus'
        ]);
    }

    public function statistics()
    {
        $totalKecamatans = Kecamatan::count();
        $kecamatansWithUmkm = Kecamatan::has('umkms')->count();
        $kecamatansWithCustomers = Kecamatan::has('customers')->count();
        $kecamatansWithoutData = Kecamatan::doesntHave('umkms')
                                    ->doesntHave('customers')
                                    ->count();

        // Kecamatan dengan UMKM terbanyak
        $mostUmkmKecamatan = Kecamatan::withCount('umkms')
            ->orderBy('umkms_count', 'desc')
            ->first();

        // Kecamatan dengan customer terbanyak
        $mostCustomerKecamatan = Kecamatan::withCount('customers')
            ->orderBy('customers_count', 'desc')
            ->first();

        return response()->json([
            'total_kecamatans' => $totalKecamatans,
            'kecamatans_with_umkm' => $kecamatansWithUmkm,
            'kecamatans_with_customers' => $kecamatansWithCustomers,
            'kecamatans_without_data' => $kecamatansWithoutData,
            'most_umkm_kecamatan' => $mostUmkmKecamatan ? [
                'name' => $mostUmkmKecamatan->nama_kecamatan,
                'count' => $mostUmkmKecamatan->umkms_count
            ] : null,
            'most_customer_kecamatan' => $mostCustomerKecamatan ? [
                'name' => $mostCustomerKecamatan->nama_kecamatan,
                'count' => $mostCustomerKecamatan->customers_count
            ] : null,
        ]);
    }

    public function adminList(Request $request)
    {
        $query = Kecamatan::withCount(['umkms', 'customers']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('nama_kecamatan', 'like', '%' . $searchTerm . '%');
        }

        // Sort options
        $sortField = $request->get('sort_field', 'nama_kecamatan');
        $sortDirection = $request->get('sort_direction', 'asc');

        $allowedSortFields = ['nama_kecamatan', 'created_at', 'updated_at', 'umkms_count', 'customers_count'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('nama_kecamatan', 'asc');
        }

        $perPage = $request->get('per_page', 10);
        $kecamatans = $query->paginate($perPage);

        return response()->json([
            'data' => KecamatanResource::collection($kecamatans),
            'meta' => [
                'total' => $kecamatans->total(),
                'per_page' => $kecamatans->perPage(),
                'current_page' => $kecamatans->currentPage(),
                'last_page' => $kecamatans->lastPage(),
            ]
        ]);
    }

    public function kecamatanDetail($id)
    {
        $kecamatan = Kecamatan::with([
            'umkms' => function($query) {
                $query->withCount('products')
                      ->orderBy('created_at', 'desc')
                      ->limit(5);
            },
            'customers' => function($query) {
                $query->orderBy('created_at', 'desc')
                      ->limit(5);
            }
        ])->withCount(['umkms', 'customers'])->findOrFail($id);

        return new KecamatanResource($kecamatan);
    }
}
