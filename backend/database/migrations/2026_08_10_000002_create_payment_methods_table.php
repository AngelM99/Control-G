<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla: payment_methods
     * Medios de pago disponibles: tarjetas de crédito/débito, efectivo, billeteras digitales.
     * Soporta bimoneda (PEN / USD).
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            // Clasificación del medio de pago
            $table->enum('tipo', ['CREDITO', 'DEBITO', 'EFECTIVO', 'BILLETERA'])
                  ->index();

            $table->string('nombre', 100);
            $table->string('banco', 100)->nullable();          // Banco emisor/custodio

            // Moneda (RN-05 bimoneda)
            $table->enum('moneda', ['PEN', 'USD'])
                  ->default('PEN')
                  ->index();

            // Campos específicos para tarjetas de crédito
            $table->decimal('linea_total', 12, 2)->nullable()
                  ->comment('Línea de crédito total del plástico');
            $table->unsignedTinyInteger('dia_corte')->nullable()
                  ->comment('Día del mes en que corta el estado de cuenta (1-31)');
            $table->unsignedTinyInteger('dia_pago')->nullable()
                  ->comment('Día del mes límite de pago (1-31)');

            // Saldo/disponible actual (calculado o ingresado manualmente)
            $table->decimal('saldo_actual', 12, 2)->default(0.00)
                  ->comment('Saldo disponible o saldo en cuenta. Negativo = deuda en tarjeta');

            // Comisión de mantenimiento mensual
            $table->decimal('comision_mantenimiento', 8, 2)->default(0.00);

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
        Schema::dropIfExists('payment_methods');
    }
};
