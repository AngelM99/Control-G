<?php

namespace App\Models;

enum Moneda: string
{
    case PEN = 'PEN';
    case USD = 'USD';

    public function simbolo(): string
    {
        return match ($this) {
            self::PEN => 'S/',
            self::USD => '$',
        };
    }
}
