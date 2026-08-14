<?php

namespace App\Models;

enum TipoMedioPago: string
{
    case Credito   = 'CREDITO';
    case Debito    = 'DEBITO';
    case Efectivo  = 'EFECTIVO';
    case Billetera = 'BILLETERA';

    public function label(): string
    {
        return match ($this) {
            self::Credito   => 'Tarjeta de Crédito',
            self::Debito    => 'Tarjeta de Débito',
            self::Efectivo  => 'Efectivo',
            self::Billetera => 'Billetera Digital',
        };
    }
}
