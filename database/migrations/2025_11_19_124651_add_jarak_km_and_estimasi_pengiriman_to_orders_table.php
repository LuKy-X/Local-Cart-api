<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('estimasi_pengiriman', 8, 2)->nullable()->after('grand_total');
            $table->decimal('jarak_km', 8, 2)->nullable()->after('estimasi_pengiriman');
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending')->nullable()->after('jarak_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('estimasi_pengiriman');
            $table->dropColumn('jarak_km');
            $table->dropColumn('status');
        });
    }
};
