<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla: categories
     * Categorías de gasto/ingreso con soporte de presupuesto límite mensual.
     * Permite jerarquía (categoría padre → subcategoría).
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Jerarquía opcional: padre → subcategoría
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('categories')
                  ->nullOnDelete();

            $table->string('nombre', 100);
            $table->enum('tipo', ['GASTO', 'INGRESO'])
                  ->index();

            // Visualización
            $table->string('icono', 60)->nullable()
                  ->comment('Nombre del ícono (e.g. heroicons: shopping-cart)');
            $table->string('color', 7)->nullable()
                  ->comment('Color hexadecimal, e.g. #4F46E5');

            // Presupuesto mensual límite (opcional)
            $table->decimal('presupuesto_limite', 12, 2)->nullable()
                  ->comment('Presupuesto mensual máximo en PEN');

            $table->enum('estado', ['ACTIVO', 'INACTIVO'])
                  ->default('ACTIVO')
                  ->index();

            $table->timestamps();

            // Índice compuesto para unicidad de nombre dentro del mismo tipo y padre
            $table->unique(['nombre', 'tipo', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
