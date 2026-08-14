<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Installment extends Model
{
    use SoftDeletes; // RN-04

    // ── Tabla ────────────────────────────────────────────────────────────────────
    protected $table = 'installments';

    // ── Asignación masiva ─────────────────────────────────────────────────────────
    protected $fillable = [
        'operation_id',
        'numero_cuota',
        'total_cuotas',
        'monto_cuota',
        'monto_abonado',
        'fecha_vencimiento',
        'fecha_pago',
        'estado',
        'notas',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'estado'            => EstadoCuota::class,
            'fecha_vencimiento' => 'date',
            'fecha_pago'        => 'date',
            'monto_cuota'       => 'decimal:2',
            'monto_abonado'     => 'decimal:2',
            'numero_cuota'      => 'integer',
            'total_cuotas'      => 'integer',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
            'deleted_at'        => 'datetime',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────────

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['PENDIENTE', 'PARCIAL']);
    }

    public function scopeVencidas($query)
    {
        return $query->where('fecha_vencimiento', '<', now()->toDateString())
                     ->whereIn('estado', ['PENDIENTE', 'PARCIAL']);
    }

    public function scopeProximasAVencer($query, int $dias = 7)
    {
        return $query->whereBetween('fecha_vencimiento', [
            now()->toDateString(),
            now()->addDays($dias)->toDateString(),
        ])->whereIn('estado', ['PENDIENTE', 'PARCIAL']);
    }

    // ── Relaciones ────────────────────────────────────────────────────────────────

    /**
     * Operación padre a la que pertenece esta cuota.
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    /**
     * Asignaciones de abono que han cubierto (total o parcialmente) esta cuota.
     */
    public function debtAllocations(): HasMany
    {
        return $this->hasMany(DebtAllocation::class, 'installment_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────────

    /**
     * Saldo pendiente de esta cuota.
     */
    public function getSaldoAttribute(): float
    {
        return round((float) $this->monto_cuota - (float) $this->monto_abonado, 2);
    }

    /**
     * Indica si la cuota está vencida y sin pagar.
     */
    public function getEstaVencidaAttribute(): bool
    {
        return in_array($this->estado?->value, ['PENDIENTE', 'PARCIAL'])
            && $this->fecha_vencimiento?->isPast();
    }

    // ── Métodos de Negocio ────────────────────────────────────────────────────────

    /**
     * Recalcula el estado de la cuota en función del monto_abonado.
     */
    public function recalcularEstado(): void
    {
        $this->estado = match (true) {
            $this->monto_abonado <= 0                       => 'PENDIENTE',
            $this->monto_abonado >= $this->monto_cuota      => 'PAGADA',
            default                                         => 'PARCIAL',
        };

        if ($this->estado === 'PAGADA') {
            $this->fecha_pago = now()->toDateString();
        }

        $this->save();
    }
}
