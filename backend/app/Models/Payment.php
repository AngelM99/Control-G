<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes; // RN-04

    // ── Tabla ────────────────────────────────────────────────────────────────────
    protected $table = 'payments';

    // ── Asignación masiva ─────────────────────────────────────────────────────────
    protected $fillable = [
        'payment_method_id',
        'monto_original',
        'moneda_original',
        'tipo_cambio',
        'monto_pen',
        'fecha_pago',
        'referencia',
        'notas',
        'comprobante_url',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'moneda_original' => Moneda::class,
            'fecha_pago'      => 'date',
            'monto_original'  => 'decimal:2',
            'tipo_cambio'     => 'decimal:4',
            'monto_pen'       => 'decimal:2',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
            'deleted_at'      => 'datetime',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────────

    public function scopeEntreFechas($query, string $desde, string $hasta)
    {
        return $query->whereBetween('fecha_pago', [$desde, $hasta]);
    }

    // ── Relaciones ────────────────────────────────────────────────────────────────

    /**
     * Medio de pago utilizado para realizar este abono.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Distribución del abono entre operaciones/cuotas.
     */
    public function debtAllocations(): HasMany
    {
        return $this->hasMany(DebtAllocation::class, 'payment_id');
    }

    /**
     * Operaciones cubiertas por este abono (acceso directo vía allocations).
     */
    public function operations()
    {
        return $this->hasManyThrough(
            Operation::class,
            DebtAllocation::class,
            'payment_id',      // FK en debt_allocations
            'id',              // PK en operations
            'id',              // PK en payments
            'operation_id'     // FK en debt_allocations → operations
        );
    }

    // ── Accessors ─────────────────────────────────────────────────────────────────

    /**
     * Total asignado a operaciones desde este payment.
     * Útil para validar que la distribución cuadre con monto_pen.
     */
    public function getTotalAsignadoAttribute(): float
    {
        return (float) $this->debtAllocations()->whereNull('deleted_at')->sum('monto_asignado');
    }

    /**
     * Saldo del payment aún no asignado a ninguna deuda.
     */
    public function getSaldoSinAsignarAttribute(): float
    {
        return round((float) $this->monto_pen - $this->total_asignado, 2);
    }
}
