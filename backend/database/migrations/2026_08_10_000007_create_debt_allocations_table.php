<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla: debt_allocations (Asignaciones de Abono)
     *
     * Tabla pivote que distribuye un payment a una o varias operaciones/cuotas.
     * Permite abonos parciales a múltiples deudas en un solo pago.
     *
     * Escenarios:
     *   payment → operation (sin cuotas)
     *   payment → installment (para operaciones con cuotas)
     *
     * RN-04: SoftDeletes habilitado.
     */
    public function up(): void
    {
        Schema::create('debt_allocations', function (Blueprint $table) {
            $table->id();

            // ── Cabecera del abono ────────────────────────────────────────────────
            $table->foreignId('payment_id')
                  ->constrained('payments')
                  ->cascadeOnDelete();

            // ── Destino del abono: operación y/o cuota ───────────────────────────
            $table->foreignId('operation_id')
                  ->constrained('operations')
                  ->restrictOnDelete()
                  ->comment('Operación destino del abono');

            $table->foreignId('installment_id')
                  ->nullable()
                  ->constrained('installments')
                  ->nullOnDelete()
                  ->comment('Cuota específica destino del abono (null = operación sin cuotas)');

            // ── Monto asignado a esta línea ───────────────────────────────────────
            $table->decimal('monto_asignado', 12, 2)
                  ->comment('Monto en PEN asignado a esta operación/cuota en este abono');

            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes(); // RN-04

            // ── Índices ───────────────────────────────────────────────────────────
            $table->index(['payment_id', 'operation_id'], 'idx_da_payment_op');
            $table->index(['operation_id', 'installment_id'], 'idx_da_op_inst');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_allocations');
    }
};
