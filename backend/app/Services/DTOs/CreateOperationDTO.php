<?php

namespace App\Services\DTOs;

use App\Models\TipoOperacion;

/**
 * DTO inmutable para crear una nueva operación.
 * Encapsula todos los datos necesarios validados antes de llegar al Service.
 */
readonly class CreateOperationDTO
{
    public function __construct(
        // ── Tipo y clasificación ─────────────────────────────────────────────────
        public TipoOperacion $tipoOperacion,
        public ?int          $contactId,
        public ?int          $paymentMethodId,
        public ?int          $categoryId,

        // ── Datos de la transacción ───────────────────────────────────────────────
        public string        $descripcion,
        public string        $fechaOperacion,         // Y-m-d
        public ?string       $fechaVencimiento,        // Y-m-d | null

        // ── RN-05 Bimoneda ────────────────────────────────────────────────────────
        public float         $montoOriginal,
        public string        $monedaOriginal,          // PEN | USD
        public float         $tipoCambio = 1.0,

        // ── Cuotas ────────────────────────────────────────────────────────────────
        public bool          $esDiferida = false,
        public int           $numeroCuotas = 1,

        /**
         * Para cuotas personalizadas (modo custom):
         * [
         *   ['monto' => 150.00, 'fecha_vencimiento' => '2026-09-10'],
         *   ['monto' => 150.00, 'fecha_vencimiento' => '2026-10-10'],
         * ]
         * Si está vacío y esDiferida=true, se calcularán cuotas iguales automáticamente.
         */
        public array         $cuotasPersonalizadas = [],

        // ── Operación origen (sólo para DEVOLUCION) ───────────────────────────────
        public ?int          $operationOrigenId = null,

        // ── Metadatos ─────────────────────────────────────────────────────────────
        public ?string       $notas = null,
        public ?string       $comprobanteUrl = null,
    ) {}

    /**
     * Calcula el monto en PEN aplicando el tipo de cambio.
     */
    public function montoPen(): float
    {
        return round($this->montoOriginal * $this->tipoCambio, 2);
    }

    /**
     * Indica si la operación requiere un contacto (tercero).
     */
    public function requiereContacto(): bool
    {
        return $this->tipoOperacion->requiereContacto();
    }

    /**
     * Indica si la operación genera deuda (PENDIENTE/PARCIAL/CANCELADO).
     */
    public function generaDeuda(): bool
    {
        return $this->tipoOperacion->generaDeuda();
    }
}
