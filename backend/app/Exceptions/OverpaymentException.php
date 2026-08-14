<?php

namespace App\Exceptions;

/**
 * Excepción de sobrepago (RN-06).
 * Se lanza cuando el monto a abonar supera el saldo pendiente de la deuda/cuota.
 */
class OverpaymentException extends BusinessRuleException
{
    public function __construct(
        float $montoIntentado,
        float $saldoPendiente,
        string $entidad = 'operación'
    ) {
        parent::__construct(
            message: sprintf(
                'Sobrepago detectado en %s: se intenta abonar S/ %.2f pero el saldo pendiente es S/ %.2f.',
                $entidad,
                $montoIntentado,
                $saldoPendiente
            ),
            rule: 'RN-06',
            context: [
                'monto_intentado'  => $montoIntentado,
                'saldo_pendiente'  => $saldoPendiente,
                'diferencia'       => round($montoIntentado - $saldoPendiente, 2),
            ],
            code: 422
        );
    }
}
