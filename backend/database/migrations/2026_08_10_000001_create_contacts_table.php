<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla: contacts
     * Almacena los contactos de terceros (deudores/acreedores).
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Datos personales
            $table->string('nombre', 150);
            $table->string('alias', 80)->nullable()->index();
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 150)->nullable();

            // Clasificación
            $table->enum('tipo_contacto', ['DEUDOR', 'ACREEDOR', 'AMBOS'])
                  ->default('AMBOS')
                  ->index();

            // Estado (RN-04 → soft delete lógico en mantenedores vía campo estado)
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])
                  ->default('ACTIVO')
                  ->index();

            $table->text('notas')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
