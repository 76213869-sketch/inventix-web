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
        // 1. Planes
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 10, 2);
            $table->integer('limite_usuarios');
            $table->integer('limite_productos');
            $table->integer('limite_sucursales');
            $table->json('caracteristicas')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Empresas
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ruc', 11)->unique();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->enum('tipo_negocio', ['bodega', 'minimarket', 'ferreteria', 'libreria', 'ropa', 'restaurante'])->default('bodega');
            $table->unsignedBigInteger('creado_por')->nullable()->index();
            $table->unsignedBigInteger('modificado_por')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Suscripciones
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('planes')->cascadeOnDelete();
            $table->string('estado')->default('demo'); // demo, activo, vencido, cancelado
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('metodo_pago')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Usuarios
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('correo')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('rol', ['super_admin', 'administrador', 'cajero', 'almacenero', 'vendedor'])->default('vendedor');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->unsignedBigInteger('creado_por')->nullable()->index();
            $table->unsignedBigInteger('modificado_por')->nullable()->index();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Password Reset Tokens (Breeze)
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // 6. Sessions (Breeze)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained('usuarios')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('suscripciones');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('planes');
    }
};
