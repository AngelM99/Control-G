<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    // ── Tabla ────────────────────────────────────────────────────────────────────
    protected $table = 'categories';

    // ── Asignación masiva ─────────────────────────────────────────────────────────
    protected $fillable = [
        'parent_id',
        'nombre',
        'tipo',
        'icono',
        'color',
        'presupuesto_limite',
        'estado',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'tipo'               => TipoCategoria::class,
            'estado'             => EstadoGeneral::class,
            'presupuesto_limite' => 'decimal:2',
            'created_at'         => 'datetime',
            'updated_at'         => 'datetime',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────────

    public function scopeActivas($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeRaiz($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // ── Relaciones ────────────────────────────────────────────────────────────────

    /**
     * Categoría padre (autorreferencia).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Subcategorías hijas.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Subcategorías activas.
     */
    public function childrenActivas(): HasMany
    {
        return $this->children()->where('estado', 'ACTIVO');
    }

    /**
     * Operaciones clasificadas bajo esta categoría.
     */
    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class, 'category_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────────

    public function getEsSubcategoriaAttribute(): bool
    {
        return $this->parent_id !== null;
    }
}
