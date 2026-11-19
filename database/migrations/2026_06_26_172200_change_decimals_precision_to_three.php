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
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('stock_actual', 10, 3)->change();
            $table->decimal('stock_minimo', 10, 3)->change();
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->decimal('cantidad', 10, 3)->change();
        });

        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->decimal('cantidad', 10, 3)->change();
        });

        Schema::table('movimientos_stock', function (Blueprint $table) {
            $table->decimal('cantidad', 10, 3)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('stock_actual', 10, 2)->change();
            $table->decimal('stock_minimo', 10, 2)->change();
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->decimal('cantidad', 10, 2)->change();
        });

        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->decimal('cantidad', 10, 2)->change();
        });

        Schema::table('movimientos_stock', function (Blueprint $table) {
            $table->decimal('cantidad', 10, 2)->change();
        });
    }
};
