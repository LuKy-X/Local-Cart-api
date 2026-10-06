<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ongkir extends Model
{
    use HasFactory;

    protected $fillable = [
        'kecamatan_asal_id',
        'kecamatan_tujuan_id',
        'tarif',
        'estimasi_hari'
    ];

    protected $casts = [
        'tarif' => 'decimal:2'
    ];

    public function kecamatanAsal()
    {
        return $this->belongsTo(Kecamatan::class, 'kecamatan_asal_id');
    }

    public function kecamatanTujuan()
    {
        return $this->belongsTo(Kecamatan::class, 'kecamatan_tujuan_id');
    }
}
