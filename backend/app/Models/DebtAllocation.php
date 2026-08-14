<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DebtAllocation extends Model
{
    use SoftDeletes; // RN-04

    // ── Tabla ────────────────────────────────────────────────────────────────────
    protected $table = 'debt_allocations';

    // ── Asignación masiva ─────────────────────────────────────────────────────────
    protected $fillable = [
        'payment_id',
        'operation_id',
        'installment_id',
        'monto_asignado',
        'notas',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'monto_asignado' => 'decimal:2',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
            'deleted_at'     => 'datetime',
        ];
    }

    // ── Relaciones ────────────────────────────────────────────────────────────────

    /**
     * Pago (cabecera) al que pertenece esta asignación.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Operación destino del abono.
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    /**
     * Cuota destino del abono (null si la operación no es diferida).
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class, 'installment_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────────

    /**
     * Indica si esta asignación aplica a una cuota específica.
     */
    public function getEsCuotaAttribute(): bool
    {
        return $this->installment_id !== null;
    }
}
