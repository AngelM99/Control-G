<?php

namespace App\Services\DTOs;

use App\Models\Operation;
use App\Models\Payment;

/**
 * Resultado devuelto por DebtPaymentService::apply().
 * Inmutable para garantizar que el caller no lo modifique post-transacción.
 */
readonly class PaymentResultDTO
{
    public function __construct(
        public Payment   $payment,
        /** @var array<int, array{operation: Operation, monto_asignado: float}> */
        public array     $operacionesAfectadas,
        public float     $totalAsignado,
        public float     $saldoSinAsignar,
        public string    $modoAsignacion,
    ) {}

    /**
     * Resumen legible para logging/respuesta API.
     */
    public function toArray(): array
    {
        return [
            'payment_id'          => $this->payment->id,
            'total_abono_pen'     => $this->payment->monto_pen,
            'total_asignado'      => $this->totalAsignado,
            'saldo_sin_asignar'   => $this->saldoSinAsignar,
            'modo_asignacion'     => $this->modoAsignacion,
            'operaciones_afectadas' => collect($this->operacionesAfectadas)
                ->map(fn($item) => [
                    'operation_id'    => $item['operation']->id,
                    'tipo'            => $item['operation']->tipo_operacion->value,
                    'monto_asignado'  => $item['monto_asignado'],
                    'estado_deuda'    => $item['operation']->estado_deuda->value,
                ])->all(),
        ];
    }
}
