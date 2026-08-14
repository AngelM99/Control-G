<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    // ── Tabla ────────────────────────────────────────────────────────────────────
    protected $table = 'contacts';

    // ── Asignación masiva ─────────────────────────────────────────────────────────
    protected $fillable = [
        'dni',
        'nombre',
        'alias',
        'telefono',
        'correo',
        'tipo_contacto',
        'estado',
        'notas',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'tipo_contacto' => TipoContacto::class,
            'estado'        => EstadoGeneral::class,
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────────

    /**
     * Filtra sólo contactos activos.
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    /**
     * Filtra por tipo de contacto.
     */
    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo_contacto', $tipo);
    }

    // ── Relaciones ────────────────────────────────────────────────────────────────

    /**
     * Operaciones vinculadas a este contacto (deudas del tercero o propias con este tercero).
     */
    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'contact_id');
    }

    /**
     * Operaciones PENDIENTES o PARCIALES (deudas activas).
     */
    public function deudasActivas(): HasMany
    {
        return $this->operations()
                    ->whereIn('estado_deuda', ['PENDIENTE', 'PARCIAL'])
                    ->whereNull('deleted_at');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────────

    /**
     * Nombre para mostrar: alias si existe, sino nombre completo.
     */
    public function getNombreDisplayAttribute(): string
    {
        return $this->alias ?? $this->nombre;
    }
}
