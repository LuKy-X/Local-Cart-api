<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'umkm_id',
        'customer_id',
        'shipper_id',
        'kode_order',
        'nomor_resi',
        'alamat_pengiriman',
        'total_harga',
        'ongkir',
        'grand_total',
        'estimasi_pengiriman',
        'jarak_km',
        'status'
    ];

    public function umkm()
    {
        return $this->belongsTo(Umkm::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function shipper()
    {
        return $this->belongsTo(Shipper::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    // Method untuk generate nomor resi
    public function generateNomorResi()
    {
        if (!$this->shipper_id) {
            return null;
        }

        $shipper = $this->shipper;
        $kodeShipper = strtoupper(substr(preg_replace('/[^A-Z]/', '', $shipper->shipper_name), 0, 3));

        if (empty($kodeShipper)) {
            $kodeShipper = 'SHIP';
        }

        $date = now()->format('Ymd');
        $orderId = str_pad($this->id, 6, '0', STR_PAD_LEFT);
        $random = strtoupper(substr(uniqid(), -3));

        return $kodeShipper . '-' . $date . '-' . $orderId . '-' . $random;
    }

    // Method untuk update shipper dengan generate nomor resi
    public function updateShipper($shipperId)
    {
        $this->shipper_id = $shipperId;
        $this->nomor_resi = $this->generateNomorResi();
        $this->save();

        return $this;
    }

    // Method untuk validasi status
    public function canUpdateToShipped()
    {
        return $this->shipper_id !== null;
    }

    // Scope untuk filter berdasarkan UMKM
    public function scopeByUmkm($query, $umkmId)
    {
        return $query->where('umkm_id', $umkmId);
    }

    public function getHasRatingAttribute()
    {
        // Check if all products in this order have been rated by the customer
        $customerId = $this->customer_id;
        $productIds = $this->orderItems->pluck('product_id')->toArray();

        $ratedProducts = Rating::where('customer_id', $customerId)
            ->where('order_id', $this->id)
            ->pluck('product_id')
            ->toArray();

        // Check if all products have been rated
        $unratedProducts = array_diff($productIds, $ratedProducts);

        return count($unratedProducts) === 0;
    }
}
