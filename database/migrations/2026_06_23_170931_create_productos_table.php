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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('productos')->cascadeOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('codigo_barras')->nullable();
            $table->enum('tipo_producto', ['estandar', 'variante', 'insumo', 'receta'])->default('estandar');
            $table->string('unidad_medida')->default('unidad');
            $table->decimal('precio_compra', 10, 2)->default(0.00);
            $table->decimal('precio_venta', 10, 2)->default(0.00);
            $table->decimal('stock_minimo', 10, 2)->default(0.00);
            $table->decimal('stock_actual', 10, 2)->default(0.00);
            $table->json('metadatos')->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->unsignedBigInteger('creado_por')->nullable()->index();
            $table->unsignedBigInteger('modificado_por')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'categoria_id']);
            $table->index(['empresa_id', 'codigo_barras']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
