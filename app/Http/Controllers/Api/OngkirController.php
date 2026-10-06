<?php

namespace App\Http\Controllers\Api;

use App\Models\Ongkir;
use Illuminate\Http\Request;
use App\Services\OngkirService;
use App\Http\Controllers\Controller;

class OngkirController extends Controller
{
    protected $ongkirService;

    public function __construct(OngkirService $ongkirService)
    {
        $this->ongkirService = $ongkirService;
    }

    /**
     * Endpoint untuk kalkulasi ongkir oleh UMKM dan Customer
     */
    public function calculateOngkirByUmkmCustomer(Request $request)
    {
        $request->validate([
            'umkm_id' => 'required|exists:umkms,id',
            'customer_id' => 'required|exists:customers,id',
        ]);

        $ongkirInfo = $this->ongkirService->calculateOngkirFromOrder(
            $request->umkm_id,
            $request->customer_id
        );

        return response()->json($ongkirInfo);
    }
}
