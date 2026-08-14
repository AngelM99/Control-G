<?php

namespace App\Models;

enum EstadoGeneral: string
{
    case Activo   = 'ACTIVO';
    case Inactivo = 'INACTIVO';

    public function label(): string
    {
        return match ($this) {
            self::Activo   => 'Activo',
            self::Inactivo => 'Inactivo',
        };
    }
}
