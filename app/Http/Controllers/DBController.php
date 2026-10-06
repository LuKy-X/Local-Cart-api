<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DBController extends Controller
{
    public function showAll()
    {
        // Ambil semua tabel dari database yang sedang dipakai
        $tables = DB::select('SHOW TABLES');

        $dbName = env('DB_DATABASE'); // nama database
        $result = [];

        foreach ($tables as $tableObj) {
            // Ambil nama kolom tabel
            $tableName = $tableObj->{"Tables_in_$dbName"};

            // Ambil semua data dari tabel
            $data = DB::table($tableName)->get();

            $result[$tableName] = $data;
        }

        return response()->json($result);
    }
}
