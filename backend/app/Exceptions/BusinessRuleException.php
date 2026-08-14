<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepción lanzada cuando una operación viola una regla de negocio del dominio.
 * Capturada en el Handler para devolver HTTP 422 con mensaje descriptivo.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  string     $message   Descripción legible de la violación
     * @param  string     $rule      Código de la regla (e.g. "RN-01", "RN-06")
     * @param  array      $context   Datos adicionales de diagnóstico
     */
    public function __construct(
        string $message,
        public readonly string $rule = '',
        public readonly array $context = [],
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error'   => 'business_rule_violation',
            'rule'    => $this->rule,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ], $this->getCode());
    }
}
