<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla: installments (Cuotas)
     *
     * Registra cada cuota individual de una operación diferida.
     * Se genera automáticamente al crear una operación con es_diferida = true.
     *
     * RN-04: SoftDeletes habilitado.
     */
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('operation_id')
                  ->constrained('operations')
                  ->cascadeOnDelete()
                  ->comment('Operación padre a la que pertenece esta cuota');

            // ── Identificación de la cuota ────────────────────────────────────────
            $table->unsignedSmallInteger('numero_cuota')
                  ->comment('Número de cuota dentro de la operación (1, 2, 3, …, N)');
            $table->unsignedSmallInteger('total_cuotas')
                  ->comment('Total de cuotas de la operación padre (desnormalizado para consultas rápidas)');

            // ── Montos ────────────────────────────────────────────────────────────
            $table->decimal('monto_cuota', 12, 2)
                  ->comment('Monto de esta cuota en PEN');
            $table->decimal('monto_abonado', 12, 2)->default(0.00)
                  ->comment('Monto abonado a esta cuota específica');

            // ── Fechas ────────────────────────────────────────────────────────────
            $table->date('fecha_vencimiento')
                  ->comment('Fecha límite de pago de esta cuota');
            $table->date('fecha_pago')->nullable()
                  ->comment('Fecha en que se canceló la cuota (nulo si pendiente)');

            // ── Estado ────────────────────────────────────────────────────────────
            $table->enum('estado', ['PENDIENTE', 'PARCIAL', 'PAGADA', 'ANULADA'])
                  ->default('PENDIENTE')
                  ->index();

            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes(); // RN-04

            // ── Índices ───────────────────────────────────────────────────────────
            $table->unique(['operation_id', 'numero_cuota'], 'uq_installment_op_num');
            $table->index(['fecha_vencimiento', 'estado'], 'idx_inst_venc_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
