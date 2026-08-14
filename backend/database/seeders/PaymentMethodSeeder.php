<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            [
                'tipo'         => 'EFECTIVO',
                'nombre'       => 'Billetera Efectivo (S/)',
                'banco'        => null,
                'moneda'       => 'PEN',
                'saldo_actual' => 0.00,
            ],
            [
                'tipo'         => 'EFECTIVO',
                'nombre'       => 'Billetera Efectivo ($)',
                'banco'        => null,
                'moneda'       => 'USD',
                'saldo_actual' => 0.00,
            ],
            [
                'tipo'         => 'BILLETERA',
                'nombre'       => 'Yape',
                'banco'        => 'BCP',
                'moneda'       => 'PEN',
                'saldo_actual' => 0.00,
            ],
            [
                'tipo'         => 'BILLETERA',
                'nombre'       => 'Plin',
                'banco'        => 'Interbank / BBVA',
                'moneda'       => 'PEN',
                'saldo_actual' => 0.00,
            ],
        ];

        foreach ($metodos as $metodo) {
            PaymentMethod::firstOrCreate(
                ['nombre' => $metodo['nombre']],
                $metodo
            );
        }
    }
}
