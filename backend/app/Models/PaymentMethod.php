<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    // ── Tabla ────────────────────────────────────────────────────────────────────
    protected $table = 'payment_methods';

    // ── Asignación masiva ─────────────────────────────────────────────────────────
    protected $fillable = [
        'tipo',
        'nombre',
        'banco',
        'moneda',
        'linea_total',
        'dia_corte',
        'dia_pago',
        'saldo_actual',
        'comision_mantenimiento',
        'estado',
        'notas',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'tipo'                    => TipoMedioPago::class,
            'moneda'                  => Moneda::class,
            'estado'                  => EstadoGeneral::class,
            'linea_total'             => 'decimal:2',
            'saldo_actual'            => 'decimal:2',
            'comision_mantenimiento'  => 'decimal:2',
            'dia_corte'               => 'integer',
            'dia_pago'                => 'integer',
            'created_at'              => 'datetime',
            'updated_at'              => 'datetime',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────────

    public function scopeActivos($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeTarjetas($query)
    {
        return $query->where('tipo', 'CREDITO');
    }

    public function scopePorMoneda($query, string $moneda)
    {
        return $query->where('moneda', $moneda);
    }

    // ── Relaciones ────────────────────────────────────────────────────────────────

    /**
     * Operaciones realizadas con este medio de pago.
     */
    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'payment_method_id');
    }

    /**
     * Abonos realizados con este medio de pago.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_method_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────────

    /**
     * Disponible de crédito: línea total menos saldo actual deudor.
     * Solo aplica a tarjetas de crédito.
     */
    public function getDisponibleAttribute(): ?float
    {
        if ($this->tipo !== 'CREDITO' || $this->linea_total === null) {
            return null;
        }

        // saldo_actual es negativo cuando hay deuda pendiente
        return $this->linea_total + $this->saldo_actual;
    }

    /**
     * Indica si la tarjeta tiene fecha de corte configurada.
     */
    public function getEsTarjetaCompletaAttribute(): bool
    {
        return $this->tipo?->value === 'CREDITO'
            && $this->dia_corte !== null
            && $this->dia_pago !== null;
    }
}
