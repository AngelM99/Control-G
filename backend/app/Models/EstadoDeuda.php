<?php

namespace App\Models;

enum EstadoDeuda: string
{
    case Pendiente  = 'PENDIENTE';
    case Parcial    = 'PARCIAL';
    case Cancelado  = 'CANCELADO';
    case Anulado    = 'ANULADO';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Parcial   => 'Parcial',
            self::Cancelado => 'Cancelado',
            self::Anulado   => 'Anulado',
        };
    }

    public function estaActiva(): bool
    {
        return in_array($this, [self::Pendiente, self::Parcial]);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'yellow',
            self::Parcial   => 'blue',
            self::Cancelado => 'green',
            self::Anulado   => 'red',
        };
    }
}
