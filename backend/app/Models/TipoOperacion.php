<?php

namespace App\Models;

/**
 * Tipos de operación del sistema Control-G.
 * Unifica todos los flujos bajo un único modelo Operation.
 */
enum TipoOperacion: string
{
    case GastoPersonal    = 'GASTO_PERSONAL';
    case IngresoPersonal  = 'INGRESO_PERSONAL';
    case CompraTercero    = 'COMPRA_TERCERO';
    case PrestamoOtorgado = 'PRESTAMO_OTORGADO';
    case PrestamoRecibido = 'PRESTAMO_RECIBIDO';
    case PagoTarjeta      = 'PAGO_TARJETA';
    case Devolucion       = 'DEVOLUCION';

    /**
     * Indica si este tipo de operación implica un tercero (contact_id requerido).
     */
    public function requiereContacto(): bool
    {
        return in_array($this, [
            self::CompraTercero,
            self::PrestamoOtorgado,
            self::PrestamoRecibido,
        ]);
    }

    /**
     * Indica si este tipo genera deuda (estado_deuda relevante).
     */
    public function generaDeuda(): bool
    {
        return in_array($this, [
            self::CompraTercero,
            self::PrestamoOtorgado,
            self::PrestamoRecibido,
        ]);
    }

    /**
     * Label de visualización en español.
     */
    public function label(): string
    {
        return match ($this) {
            self::GastoPersonal    => 'Gasto Personal',
            self::IngresoPersonal  => 'Ingreso Personal',
            self::CompraTercero    => 'Compra para Tercero',
            self::PrestamoOtorgado => 'Préstamo Otorgado',
            self::PrestamoRecibido => 'Préstamo Recibido',
            self::PagoTarjeta      => 'Pago de Tarjeta',
            self::Devolucion       => 'Devolución',
        };
    }
}
