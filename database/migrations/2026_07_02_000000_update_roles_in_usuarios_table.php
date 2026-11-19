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
        // Modificar columna ENUM para soportar 'gerente' y 'empleado' además de los anteriores
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('super_admin', 'administrador', 'gerente', 'cajero', 'empleado', 'almacenero', 'vendedor') DEFAULT 'empleado'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('super_admin', 'administrador', 'cajero', 'almacenero', 'vendedor') DEFAULT 'vendedor'");
    }
};
