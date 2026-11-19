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
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->date('fecha_apertura');
            $table->time('hora_apertura');
            $table->date('fecha_cierre')->nullable();
            $table->time('hora_cierre')->nullable();
            $table->decimal('monto_inicial', 10, 2);
            $table->decimal('monto_final', 10, 2)->nullable();
            $table->decimal('monto_esperado', 10, 2)->default(0.00);
            $table->decimal('diferencia', 10, 2)->nullable();
            $table->text('motivo_diferencia')->nullable();
            $table->text('observacion_apertura')->nullable();
            $table->text('observacion_cierre')->nullable();
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->enum('tipo', ['apertura', 'venta', 'gasto', 'retiro', 'ingreso', 'cierre']);
            $table->decimal('monto', 10, 2);
            $table->text('descripcion')->nullable();
            $table->date('fecha');
            $table->time('hora');
            $table->timestamps();
        });

        Schema::create('gastos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('categoria'); // Agua, Luz, Internet, Movilidad, Proveedor, Otros
            $table->decimal('monto', 10, 2);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('retiros_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->text('motivo')->nullable();
            $table->timestamps();
        });

        Schema::create('ingresos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['caja_id']);
            $table->dropColumn('caja_id');
        });

        Schema::dropIfExists('ingresos_caja');
        Schema::dropIfExists('retiros_caja');
        Schema::dropIfExists('gastos_caja');
        Schema::dropIfExists('movimientos_caja');
        Schema::dropIfExists('cajas');
    }
};
