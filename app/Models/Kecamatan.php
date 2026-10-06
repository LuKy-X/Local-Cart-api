<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Kecamatan extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_kecamatan',
        'latitude',
        'longitude'
    ];

    public function umkms()
    {
        return $this->hasMany(Umkm::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
