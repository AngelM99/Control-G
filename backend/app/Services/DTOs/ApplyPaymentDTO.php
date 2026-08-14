<?php

namespace App\Services\DTOs;

/**
 * DTO inmutable para registrar un abono a una o varias deudas.
 */
readonly class ApplyPaymentDTO
{
    public function __construct(
        // ── Monto del abono ───────────────────────────────────────────────────────
        public float   $montoOriginal,
        public string  $monedaOriginal,   // PEN | USD
        public float   $tipoCambio = 1.0,

        // ── Medio de pago utilizado ───────────────────────────────────────────────
        public ?int    $paymentMethodId = null,

        // ── Fecha y referencia ────────────────────────────────────────────────────
        public string  $fechaPago = '',   // Y-m-d; vacío = today
        public ?string $referencia = null,
        public ?string $notas = null,
        public ?string $comprobanteUrl = null,

        // ── Modo de asignación ────────────────────────────────────────────────────
        /**
         * 'auto'   → FIFO automático entre las deudas pendientes del contacto
         * 'manual' → usar asignacionesManual para distribuir
         */
        public string $modoAsignacion = 'auto',

        /**
         * Solo para modoAsignacion = 'manual'.
         * Formato:
         * [
         *   ['operation_id' => 5, 'installment_id' => null, 'monto' => 200.00],
         *   ['operation_id' => 5, 'installment_id' => 12,   'monto' => 100.00],
         * ]
         */
        public array   $asignacionesManual = [],

        // ── Filtro para FIFO ──────────────────────────────────────────────────────
        /** ID del contacto cuyas deudas se pagarán en modo auto */
        public ?int    $contactId = null,

        /** Si se quiere limitar FIFO a un solo tipo de operación */
        public ?string $tipoOperacion = null,
    ) {}

    /**
     * Calcula el monto total del abono en PEN.
     */
    public function montoPen(): float
    {
        return round($this->montoOriginal * $this->tipoCambio, 2);
    }

    /**
     * Fecha de pago resuelta (hoy si no se especificó).
     */
    public function fechaPagoResuelta(): string
    {
        return $this->fechaPago ?: now()->toDateString();
    }
}
