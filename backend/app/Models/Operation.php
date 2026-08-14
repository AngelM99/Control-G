<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operation extends Model
{
    use SoftDeletes; // RN-04

    // ── Tabla ────────────────────────────────────────────────────────────────────
    protected $table = 'operations';

    // ── Asignación masiva ─────────────────────────────────────────────────────────
    protected $fillable = [
        'tipo_operacion',
        'contact_id',
        'payment_method_id',
        'category_id',
        'operation_origen_id',
        'descripcion',
        'fecha_operacion',
        'fecha_vencimiento',
        'monto_original',
        'moneda_original',
        'tipo_cambio',
        'monto_pen',
        'es_diferida',
        'numero_cuotas',
        'estado_deuda',
        'monto_abonado',
        'notas',
        'comprobante_url',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'tipo_operacion'   => TipoOperacion::class,
            'moneda_original'  => Moneda::class,
            'estado_deuda'     => EstadoDeuda::class,
            'fecha_operacion'  => 'date',
            'fecha_vencimiento'=> 'date',
            'monto_original'   => 'decimal:2',
            'tipo_cambio'      => 'decimal:4',
            'monto_pen'        => 'decimal:2',
            'monto_abonado'    => 'decimal:2',
            'monto_saldo'      => 'decimal:2',  // columna generada
            'es_diferida'      => 'boolean',
            'numero_cuotas'    => 'integer',
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
            'deleted_at'       => 'datetime',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────────

    /**
     * Solo operaciones que generan deuda con terceros.
     */
    public function scopeConDeuda($query)
    {
        return $query->whereIn('tipo_operacion', [
            'COMPRA_TERCERO',
            'PRESTAMO_OTORGADO',
            'PRESTAMO_RECIBIDO',
        ]);
    }

    /**
     * Deudas activas (PENDIENTE o PARCIAL).
     */
    public function scopeDeudasActivas($query)
    {
        return $query->whereIn('estado_deuda', ['PENDIENTE', 'PARCIAL']);
    }

    /**
     * Filtra por rango de fechas de operación.
     */
    public function scopeEntreFechas($query, string $desde, string $hasta)
    {
        return $query->whereBetween('fecha_operacion', [$desde, $hasta]);
    }

    /**
     * Filtra operaciones de gasto (propio o de tercero).
     */
    public function scopeGastos($query)
    {
        return $query->whereIn('tipo_operacion', [
            'GASTO_PERSONAL',
            'COMPRA_TERCERO',
            'PAGO_TARJETA',
        ]);
    }

    /**
     * Filtra ingresos.
     */
    public function scopeIngresos($query)
    {
        return $query->whereIn('tipo_operacion', [
            'INGRESO_PERSONAL',
            'DEVOLUCION',
        ]);
    }

    // ── Relaciones ────────────────────────────────────────────────────────────────

    /**
     * Contacto tercero relacionado (deudor/acreedor).
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Medio de pago utilizado.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Categoría clasificatoria.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Operación origen (para devoluciones).
     */
    public function operationOrigen(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'operation_origen_id');
    }

    /**
     * Devoluciones originadas por esta operación.
     */
    public function devoluciones(): HasMany
    {
        return $this->hasMany(Operation::class, 'operation_origen_id');
    }

    /**
     * Cuotas de esta operación diferida.
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'operation_id')
                    ->orderBy('numero_cuota');
    }

    /**
     * Cuotas pendientes.
     */
    public function installmentsPendientes(): HasMany
    {
        return $this->installments()
                    ->whereIn('estado', ['PENDIENTE', 'PARCIAL']);
    }

    /**
     * Líneas de asignación de abonos a esta operación.
     */
    public function debtAllocations(): HasMany
    {
        return $this->hasMany(DebtAllocation::class, 'operation_id');
    }

    // ── Métodos de Negocio ────────────────────────────────────────────────────────

    /**
     * Recalcula y actualiza estado_deuda y monto_abonado tras un abono.
     * Debe llamarse dentro de una transacción DB.
     */
    public function recalcularEstado(): void
    {
        $totalAbonado = $this->debtAllocations()
                             ->whereNull('deleted_at')
                             ->sum('monto_asignado');

        $this->monto_abonado = $totalAbonado;

        $this->estado_deuda = match (true) {
            $totalAbonado <= 0            => 'PENDIENTE',
            $totalAbonado >= $this->monto_pen => 'CANCELADO',
            default                       => 'PARCIAL',
        };

        $this->save();
    }

    // ── Accessors ─────────────────────────────────────────────────────────────────

    /**
     * Saldo pendiente por pagar/cobrar de la operación.
     */
    public function getMontoSaldoAttribute(): float
    {
        return round((float) $this->monto_pen - (float) $this->monto_abonado, 2);
    }

    /**
     * Indica si la operación genera una deuda con un tercero.
     */
    public function getGeneraDudaTerceroAttribute(): bool
    {
        return in_array($this->tipo_operacion?->value, [
            'COMPRA_TERCERO',
            'PRESTAMO_OTORGADO',
            'PRESTAMO_RECIBIDO',
        ]);
    }

    /**
     * Indica si la operación requiere contacto (tercero).
     */
    public function getRequiereContactoAttribute(): bool
    {
        return $this->genera_duda_tercero;
    }
}
