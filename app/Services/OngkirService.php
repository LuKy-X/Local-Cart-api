<?php

namespace App\Services;

use App\Models\Ongkir;
use App\Models\Umkm;
use App\Models\Customer;

class OngkirService
{
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Radius bumi dalam kilometer

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c; // Jarak dalam kilometer
    }

    /**
     * Hitung ongkir berdasarkan jarak
     */
    private function calculateShippingCost($distance)
    {
        // Tarif dasar: Rp 2,500 untuk 15km pertama
        $baseCost = 2500;
        $baseDistance = 15;

        // Tarif per km setelah 15km: Rp 1,000 per km
        $costPerKm = 1000;

        if ($distance <= $baseDistance) {
            return $baseCost;
        }

        $additionalDistance = $distance - $baseDistance;
        $additionalCost = $additionalDistance * $costPerKm;

        return $baseCost + $additionalCost;
    }

    /**
     * Hitung estimasi hari pengiriman berdasarkan jarak
     */
    private function calculateEstimatedDays($distance)
    {
        if ($distance <= 10) {
            return 1; // 1 hari untuk <= 10km
        } elseif ($distance <= 25) {
            return 2; // 2 hari untuk 11-25km
        } elseif ($distance <= 50) {
            return 3; // 3 hari untuk 26-50km
        } else {
            return 4; // 4 hari untuk >50km
        }
    }

    /**
     * Method utama: Hitung ongkir antara UMKM dan Customer
     */
    public function calculateOngkir($umkm, $customer)
    {
        // Jika UMKM dan Customer berada di kecamatan yang sama
        if ($umkm->kecamatan_id === $customer->kecamatan_id) {
            return [
                'tarif' => 0,
                'estimasi_hari' => 1,
                'keterangan' => 'Gratis ongkir dalam kecamatan',
                'jarak_km' => 0,
                'kecamatan_asal' => $umkm->kecamatan->nama_kecamatan ?? 'Tidak diketahui',
                'kecamatan_tujuan' => $customer->kecamatan->nama_kecamatan ?? 'Tidak diketahui'
            ];
        }

        // Dapatkan data kecamatan
        $kecamatanAsal = $umkm->kecamatan;
        $kecamatanTujuan = $customer->kecamatan;

        // Validasi data kecamatan
        if (!$kecamatanAsal || !$kecamatanTujuan) {
            return $this->getDefaultOngkir('Data kecamatan tidak lengkap');
        }

        // Validasi koordinat
        if (!$kecamatanAsal->latitude || !$kecamatanAsal->longitude ||
            !$kecamatanTujuan->latitude || !$kecamatanTujuan->longitude) {
            return $this->getDefaultOngkir('Koordinat kecamatan tidak tersedia');
        }

        try {
            // Hitung jarak
            $distance = $this->calculateDistance(
                $kecamatanAsal->latitude,
                $kecamatanAsal->longitude,
                $kecamatanTujuan->latitude,
                $kecamatanTujuan->longitude
            );

            // Bulatkan jarak ke 1 decimal
            $distance = round($distance, 1);

            // Hitung ongkir dan estimasi
            $tarif = $this->calculateShippingCost($distance);
            $estimasiHari = $this->calculateEstimatedDays($distance);

            return [
                'tarif' => $tarif,
                'estimasi_hari' => $estimasiHari,
                'keterangan' => 'Ongkir berdasarkan jarak',
                'jarak_km' => $distance,
                'kecamatan_asal' => $kecamatanAsal->nama_kecamatan,
                'kecamatan_tujuan' => $kecamatanTujuan->nama_kecamatan
            ];
        } catch (\Exception $e) {
            return $this->getDefaultOngkir('Error dalam perhitungan: ' . $e->getMessage());
        }
    }

    /**
     * Helper method untuk ongkir default ketika ada error
     */
    private function getDefaultOngkir($reason)
    {
        return [
            'tarif' => 15000,
            'estimasi_hari' => 3,
            'keterangan' => 'Ongkir default (' . $reason . ')',
            'jarak_km' => null,
            'kecamatan_asal' => 'Tidak diketahui',
            'kecamatan_tujuan' => 'Tidak diketahui'
        ];
    }

    /**
     * Method praktis: Hitung ongkir dari ID UMKM dan Customer
     */
    public function calculateOngkirFromOrder($umkmId, $customerId)
    {
        $umkm = Umkm::with('kecamatan')->findOrFail($umkmId);
        $customer = Customer::with('kecamatan')->findOrFail($customerId);

        return $this->calculateOngkir($umkm, $customer);
    }
}
