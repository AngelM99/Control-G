<?php

namespace App\Models;

enum TipoContacto: string
{
    case Deudor   = 'DEUDOR';
    case Acreedor = 'ACREEDOR';
    case Ambos    = 'AMBOS';

    public function label(): string
    {
        return match ($this) {
            self::Deudor   => 'Deudor',
            self::Acreedor => 'Acreedor',
            self::Ambos    => 'Ambos',
        };
    }
}
