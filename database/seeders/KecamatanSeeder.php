<?php

namespace Database\Seeders;

use App\Models\Kecamatan;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class KecamatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kecamatans = [
            [
                'nama_kecamatan' => 'Karanganyar',
                'latitude' => -6.2088,
                'longitude' => 106.8456
            ],
            [
                'nama_kecamatan' => 'Jumantono',
                'latitude' => -6.2297,
                'longitude' => 106.6894
            ],
            [
                'nama_kecamatan' => 'Jumapolo',
                'latitude' => -6.1745,
                'longitude' => 106.8227
            ],
            [
                'nama_kecamatan' => 'Karangpandan',
                'latitude' => -6.1352,
                'longitude' => 106.8133
            ],
            [
                'nama_kecamatan' => 'Jaten',
                'latitude' => -6.2976,
                'longitude' => 106.6391
            ],
        ];

        foreach ($kecamatans as $kecamatan) {
            Kecamatan::create($kecamatan);
        }
    }
}
