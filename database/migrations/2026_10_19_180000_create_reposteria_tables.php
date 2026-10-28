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
        // 1. Ingredientes y Materiales
        Schema::create('ingredientes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->nullable();
            $table->string('nombre');
            $table->string('categoria')->default('Harinas y Azúcares');
            $table->text('descripcion')->nullable();
            $table->string('unidad_base', 20)->default('kg'); // kg, g, L, ml, pza, paquete
            $table->decimal('stock_actual', 14, 4)->default(0);
            $table->decimal('stock_minimo', 14, 4)->default(0);
            $table->decimal('costo_unitario', 14, 4)->default(0); // Costo por unidad base
            $table->date('fecha_ultima_compra')->nullable();
            $table->date('fecha_caducidad')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('imagen')->nullable();
            $table->timestamps();
        });

        // 2. Movimientos de Inventario
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingrediente_id')->constrained('ingredientes')->onDelete('restrict');
            $table->string('tipo_movimiento', 50); // entrada_compra, salida_costeo, merma, ajuste_positivo, ajuste_negativo, reversion_costeo
            $table->decimal('cantidad', 14, 4);
            $table->string('unidad', 20);
            $table->decimal('existencia_anterior', 14, 4);
            $table->decimal('existencia_posterior', 14, 4);
            $table->text('motivo')->nullable();
            $table->string('referencia_tipo', 50)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 3. Pedidos
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 50)->unique();
            $table->string('cliente_nombre');
            $table->string('cliente_telefono', 50);
            $table->date('fecha_registro');
            $table->date('fecha_entrega');
            $table->time('hora_entrega');
            $table->dateTime('fecha_entrega_real')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('sabor');
            $table->string('tamano');
            $table->string('relleno')->nullable();
            $table->string('decoracion_tipo')->nullable();
            $table->text('dedicatoria')->nullable();
            $table->text('foto_referencia')->nullable();
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_sugerido', 12, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->enum('estado', ['pendiente', 'en_preparacion', 'listo', 'entregado', 'cancelado'])->default('pendiente');
            $table->timestamps();
        });

        // 4. Recetas
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('fotografia')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('sabor')->nullable();
            $table->string('tamano_rendimiento')->nullable();
            $table->integer('tiempo_preparacion')->default(0); // minutos
            $table->integer('tiempo_horneado')->default(0); // minutos
            $table->text('instrucciones')->nullable();
            $table->timestamps();
        });

        // 5. Ingredientes de Recetas
        Schema::create('receta_ingredientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receta_id')->constrained('recetas')->onDelete('cascade');
            $table->foreignId('ingrediente_id')->nullable()->constrained('ingredientes')->onDelete('set null');
            $table->string('nombre_ingrediente');
            $table->decimal('cantidad', 14, 4);
            $table->string('unidad', 20);
            $table->timestamps();
        });

        // 6. Costeos Libres
        Schema::create('costeos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 50)->unique();
            $table->string('nombre_preparacion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->integer('porciones')->default(1);
            $table->decimal('costo_por_porcion', 14, 2)->default(0);
            $table->string('estado', 30)->default('confirmado'); // confirmado, revertido
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // 7. Detalles del Costeo
        Schema::create('costeo_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costeo_id')->constrained('costeos')->onDelete('cascade');
            $table->foreignId('ingrediente_id')->constrained('ingredientes')->onDelete('restrict');
            $table->decimal('cantidad', 14, 4);
            $table->string('unidad', 20);
            $table->decimal('cantidad_base', 14, 4);
            $table->decimal('costo_unitario_snapshot', 14, 4);
            $table->decimal('costo_subtotal', 14, 2);
            $table->timestamps();
        });

        // 8. Compras
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('folio_ticket', 100)->nullable();
            $table->string('establecimiento');
            $table->date('fecha_compra');
            $table->decimal('importe_total', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 9. Detalles de Compras
        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->onDelete('cascade');
            $table->foreignId('ingrediente_id')->constrained('ingredientes')->onDelete('restrict');
            $table->decimal('cantidad', 14, 4);
            $table->string('unidad', 20);
            $table->decimal('cantidad_base', 14, 4);
            $table->decimal('costo_unitario', 14, 4);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('costeo_detalles');
        Schema::dropIfExists('costeos');
        Schema::dropIfExists('receta_ingredientes');
        Schema::dropIfExists('recetas');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('ingredientes');
    }
};
