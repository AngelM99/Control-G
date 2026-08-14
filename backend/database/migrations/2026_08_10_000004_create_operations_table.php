<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla: operations
     *
     * Entidad central del sistema. Unifica 7 tipos de operación bajo un modelo polimórfico:
     *  - GASTO_PERSONAL      → Gasto propio sin tercero
     *  - INGRESO_PERSONAL    → Ingreso propio sin tercero
     *  - COMPRA_TERCERO      → Compra realizada para un tercero (genera deuda del tercero)
     *  - PRESTAMO_OTORGADO   → Préstamo dado a un tercero (genera deuda del tercero)
     *  - PRESTAMO_RECIBIDO   → Préstamo recibido de un tercero (genera deuda propia)
     *  - PAGO_TARJETA        → Pago de cuota/saldo de tarjeta de crédito
     *  - DEVOLUCION          → Devolución de dinero (reversa de operación previa)
     *
     * RN-05 Bimoneda:
     *   monto_original + moneda_original + tipo_cambio = monto_pen (siempre en PEN)
     *
     * RN-04 Soft Deletes: deleted_at
     */
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();

            // ── Tipo de operación ────────────────────────────────────────────────────
            $table->enum('tipo_operacion', [
                'GASTO_PERSONAL',
                'INGRESO_PERSONAL',
                'COMPRA_TERCERO',
                'PRESTAMO_OTORGADO',
                'PRESTAMO_RECIBIDO',
                'PAGO_TARJETA',
                'DEVOLUCION',
            ])->index();

            // ── Relaciones con mantenedores ──────────────────────────────────────────
            $table->foreignId('contact_id')
                  ->nullable()
                  ->constrained('contacts')
                  ->restrictOnDelete()
                  ->comment('Nulo para GASTO_PERSONAL e INGRESO_PERSONAL');

            $table->foreignId('payment_method_id')
                  ->nullable()
                  ->constrained('payment_methods')
                  ->restrictOnDelete()
                  ->comment('Medio de pago utilizado');

            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained('categories')
                  ->nullOnDelete();

            // ── Autorreferencia: operación original en caso de DEVOLUCION ────────────
            $table->foreignId('operation_origen_id')
                  ->nullable()
                  ->constrained('operations')
                  ->nullOnDelete()
                  ->comment('Apunta a la operación que origina esta devolución');

            // ── Datos de la operación ────────────────────────────────────────────────
            $table->string('descripcion', 255);
            $table->date('fecha_operacion')->index();
            $table->date('fecha_vencimiento')->nullable()
                  ->comment('Fecha límite de pago de la deuda generada');

            // ── RN-05 Bimoneda ───────────────────────────────────────────────────────
            $table->decimal('monto_original', 12, 2)
                  ->comment('Monto en la moneda original de la transacción');
            $table->enum('moneda_original', ['PEN', 'USD'])
                  ->default('PEN');
            $table->decimal('tipo_cambio', 8, 4)
                  ->default(1.0000)
                  ->comment('Tipo de cambio al momento de la operación (1.0 si es PEN)');
            $table->decimal('monto_pen', 12, 2)
                  ->comment('Monto equivalente en PEN = monto_original * tipo_cambio');

            // ── Cuotas ───────────────────────────────────────────────────────────────
            $table->boolean('es_diferida')->default(false)
                  ->comment('TRUE si la operación se divide en cuotas');
            $table->unsignedSmallInteger('numero_cuotas')->default(1)
                  ->comment('Cantidad total de cuotas (1 = pago único)');

            // ── Estado de la deuda generada ──────────────────────────────────────────
            $table->enum('estado_deuda', [
                'PENDIENTE',
                'PARCIAL',
                'CANCELADO',
                'ANULADO',
            ])->default('PENDIENTE')->index();

            // ── Montos de control (calculados) ───────────────────────────────────────
            $table->decimal('monto_abonado', 12, 2)->default(0.00)
                  ->comment('Total abonado hasta la fecha en PEN');
            $table->decimal('monto_saldo', 12, 2)
                  ->storedAs('monto_pen - monto_abonado')
                  ->comment('Saldo pendiente (columna generada por MySQL)');

            // ── Metadatos ────────────────────────────────────────────────────────────
            $table->text('notas')->nullable();
            $table->string('comprobante_url', 500)->nullable()
                  ->comment('URL/path al comprobante adjunto');

            $table->timestamps();
            $table->softDeletes(); // RN-04

            // ── Índices compuestos ───────────────────────────────────────────────────
            $table->index(['contact_id', 'estado_deuda'], 'idx_ops_contact_estado');
            $table->index(['fecha_operacion', 'tipo_operacion'], 'idx_ops_fecha_tipo');
            $table->index(['payment_method_id', 'fecha_operacion'], 'idx_ops_pm_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
