<?php

namespace App\Models;

enum TipoCategoria: string
{
    case Gasto   = 'GASTO';
    case Ingreso = 'INGRESO';

    public function label(): string
    {
        return match ($this) {
            self::Gasto   => 'Gasto',
            self::Ingreso => 'Ingreso',
        };
    }
}
