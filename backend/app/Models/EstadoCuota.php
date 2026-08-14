<?php

namespace App\Models;

enum EstadoCuota: string
{
    case Pendiente = 'PENDIENTE';
    case Parcial   = 'PARCIAL';
    case Pagada    = 'PAGADA';
    case Anulada   = 'ANULADA';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Parcial   => 'Parcial',
            self::Pagada    => 'Pagada',
            self::Anulada   => 'Anulada',
        };
    }
}
