<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla: payments (Cabecera de Abono)
     *
     * Representa un pago o abono realizado (puede ser en efectivo, transferencia, etc.).
     * Un solo payment puede distribuirse a múltiples operaciones/cuotas
     * a través de la tabla pivot debt_allocations.
     *
     * RN-04: SoftDeletes habilitado.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // ── Medio de pago utilizado para este abono ───────────────────────────
            $table->foreignId('payment_method_id')
                  ->nullable()
                  ->constrained('payment_methods')
                  ->restrictOnDelete();

            // ── RN-05 Bimoneda ────────────────────────────────────────────────────
            $table->decimal('monto_original', 12, 2);
            $table->enum('moneda_original', ['PEN', 'USD'])->default('PEN');
            $table->decimal('tipo_cambio', 8, 4)->default(1.0000);
            $table->decimal('monto_pen', 12, 2)
                  ->comment('Total del abono convertido a PEN');

            // ── Metadatos ─────────────────────────────────────────────────────────
            $table->date('fecha_pago')->index();
            $table->string('referencia', 150)->nullable()
                  ->comment('Número de operación bancaria, voucher, etc.');
            $table->text('notas')->nullable();
            $table->string('comprobante_url', 500)->nullable();

            $table->timestamps();
            $table->softDeletes(); // RN-04
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
