<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Contact;
use App\Models\Installment;
use App\Models\Operation;
use App\Models\PaymentMethod;
use App\Models\TipoOperacion;
use App\Services\DTOs\CreateOperationDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * OperationService — Servicio de Dominio para el registro de operaciones.
 *
 * Implementa la Matriz 3.1 del SRS y las siguientes reglas de negocio:
 *   RN-01 → Compras a terceros y préstamos NO cuentan como gasto personal del mes.
 *   RN-05 → Bimoneda: monto_pen = monto_original × tipo_cambio.
 *   RN-08 → Si es_diferida=true, se generan N cuotas en la tabla installments.
 *   RN-03 → El tipo de cambio se registra en el momento de la operación (inmutable).
 */
class OperationService
{
    // ──────────────────────────────────────────────────────────────────────────────
    // Punto de entrada principal
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Registra una operación y, si corresponde, genera sus cuotas.
     * Todo se ejecuta dentro de una única transacción DB.
     *
     * @param  CreateOperationDTO $dto  Datos validados desde el FormRequest
     * @return Operation               Operación persistida con sus relaciones
     * @throws BusinessRuleException   Si viola una RN del dominio
     */
    public function register(CreateOperationDTO $dto): Operation
    {
        return DB::transaction(function () use ($dto) {
            // ── 1. Validaciones de dominio previas a la escritura ────────────────
            $this->validarReglasDeDominio($dto);

            // ── 2. Construir el modelo Operation ────────────────────────────────
            $operation = $this->construirOperation($dto);
            $operation->save();

            // ── 3. Generar cuotas si la operación es diferida ───────────────────
            if ($dto->esDiferida && $dto->numeroCuotas > 1) {
                $this->generarCuotas($operation, $dto);
            }

            // ── 4. Ajustar saldo del medio de pago si aplica ────────────────────
            if ($operation->payment_method_id) {
                $this->ajustarSaldoMedioPago($operation);
            }

            return $operation->load(['contact', 'paymentMethod', 'category', 'installments']);
        });
    }

    /**
     * Anula (soft delete) una operación y todas sus cuotas pendientes.
     * RN-04: Nunca se elimina físicamente.
     *
     * @throws BusinessRuleException Si la operación ya tiene abonos registrados
     */
    public function anular(Operation $operation, string $motivo = ''): Operation
    {
        return DB::transaction(function () use ($operation, $motivo) {
            if ($operation->monto_abonado > 0) {
                throw new BusinessRuleException(
                    'No se puede anular una operación que ya tiene abonos registrados. Use la reversión.',
                    rule: 'RN-04',
                    context: ['operation_id' => $operation->id, 'monto_abonado' => $operation->monto_abonado]
                );
            }

            // Marcar estado como ANULADO antes del soft delete
            $operation->update([
                'estado_deuda' => 'ANULADO',
                'notas'        => trim(($operation->notas ?? '') . "\n[ANULADO] " . $motivo),
            ]);

            // Anular cuotas pendientes
            $operation->installments()
                      ->whereIn('estado', ['PENDIENTE', 'PARCIAL'])
                      ->update(['estado' => 'ANULADA']);

            // Soft delete RN-04
            $operation->delete();
            $operation->installments()->each(fn($i) => $i->delete());

            // Revertir ajuste de saldo si aplica
            if ($operation->payment_method_id) {
                $this->revertirSaldoMedioPago($operation);
            }

            return $operation->refresh();
        });
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Validaciones de Reglas de Negocio
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Valida todas las RN aplicables antes de persistir.
     *
     * @throws BusinessRuleException
     */
    private function validarReglasDeDominio(CreateOperationDTO $dto): void
    {
        // RN-03: Tipo de cambio requerido para moneda USD
        if ($dto->monedaOriginal === 'USD' && $dto->tipoCambio <= 0) {
            throw new BusinessRuleException(
                'El tipo de cambio debe ser mayor a 0 para operaciones en USD.',
                rule: 'RN-03',
                context: ['tipo_cambio' => $dto->tipoCambio]
            );
        }

        // RN-01: Compras a terceros y préstamos NO son gasto personal
        // (Esta RN no bloquea el registro, es informativa para reportes;
        //  se garantiza por la estructura: sólo GASTO_PERSONAL e INGRESO_PERSONAL
        //  afectan los acumuladores personales del dashboard)

        // Contacto requerido para operaciones con terceros
        if ($dto->requiereContacto() && empty($dto->contactId)) {
            throw new BusinessRuleException(
                "El tipo de operación '{$dto->tipoOperacion->label()}' requiere un contacto (tercero).",
                rule: 'RN-01',
                context: ['tipo_operacion' => $dto->tipoOperacion->value]
            );
        }

        // Contacto NO debe indicarse en operaciones personales
        if (!$dto->requiereContacto() && !empty($dto->contactId)
            && !in_array($dto->tipoOperacion, [TipoOperacion::Devolucion, TipoOperacion::PagoTarjeta])) {
            throw new BusinessRuleException(
                "El tipo '{$dto->tipoOperacion->label()}' no debe tener contacto asociado.",
                rule: 'RN-01',
                context: ['tipo_operacion' => $dto->tipoOperacion->value]
            );
        }

        // Cuotas: si es diferida debe tener > 1 cuota
        if ($dto->esDiferida && $dto->numeroCuotas < 2) {
            throw new BusinessRuleException(
                'Una operación diferida debe tener al menos 2 cuotas.',
                rule: 'RN-08',
                context: ['numero_cuotas' => $dto->numeroCuotas]
            );
        }

        // Validar que cuotas personalizadas cuadren con el monto total
        if (!empty($dto->cuotasPersonalizadas)) {
            $this->validarCuotasPersonalizadas($dto);
        }

        // DEVOLUCION: debe referenciar una operación origen válida
        if ($dto->tipoOperacion === TipoOperacion::Devolucion) {
            if (empty($dto->operationOrigenId)) {
                throw new BusinessRuleException(
                    'Una devolución debe referenciar la operación original.',
                    rule: 'RN-07',
                    context: ['tipo_operacion' => 'DEVOLUCION']
                );
            }
            $origen = Operation::find($dto->operationOrigenId);
            if (!$origen) {
                throw new BusinessRuleException(
                    "La operación origen (ID: {$dto->operationOrigenId}) no existe.",
                    rule: 'RN-07'
                );
            }
        }

        // El pago de tarjeta se valida en CreditCardService.
    }

    /**
     * Valida que las cuotas personalizadas sumen exactamente el monto_pen.
     */
    private function validarCuotasPersonalizadas(CreateOperationDTO $dto): void
    {
        $montoPen    = $dto->montoPen();
        $sumaMontos  = round(collect($dto->cuotasPersonalizadas)->sum('monto'), 2);

        if (abs($sumaMontos - $montoPen) > 0.01) {
            throw new BusinessRuleException(
                sprintf(
                    'La suma de cuotas personalizadas (S/ %.2f) no coincide con el monto total (S/ %.2f).',
                    $sumaMontos,
                    $montoPen
                ),
                rule: 'RN-08',
                context: [
                    'suma_cuotas' => $sumaMontos,
                    'monto_pen'   => $montoPen,
                    'diferencia'  => round($sumaMontos - $montoPen, 2),
                ]
            );
        }

        if (count($dto->cuotasPersonalizadas) !== $dto->numeroCuotas) {
            throw new BusinessRuleException(
                sprintf(
                    'El número de cuotas personalizadas (%d) no coincide con numero_cuotas (%d).',
                    count($dto->cuotasPersonalizadas),
                    $dto->numeroCuotas
                ),
                rule: 'RN-08'
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Construcción del modelo
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Construye el objeto Operation sin persistir.
     *
     * MATRIZ 3.1 — Estado inicial según tipo:
     *   GASTO_PERSONAL    → estado_deuda = CANCELADO (no genera deuda)
     *   INGRESO_PERSONAL  → estado_deuda = CANCELADO
     *   COMPRA_TERCERO    → estado_deuda = PENDIENTE (el tercero debe)
     *   PRESTAMO_OTORGADO → estado_deuda = PENDIENTE (el tercero debe)
     *   PRESTAMO_RECIBIDO → estado_deuda = PENDIENTE (yo debo)
     *   PAGO_TARJETA      → estado_deuda = CANCELADO (es un pago, no deuda)
     *   DEVOLUCION        → estado_deuda = CANCELADO
     */
    private function construirOperation(CreateOperationDTO $dto): Operation
    {
        $montoPen = $dto->montoPen();

        // RN-01: Determinar estado inicial de deuda por tipo de operación
        $estadoDeuda = $dto->generaDeuda() ? 'PENDIENTE' : 'CANCELADO';

        return new Operation([
            'tipo_operacion'      => $dto->tipoOperacion->value,
            'contact_id'          => $dto->contactId,
            'payment_method_id'   => $dto->paymentMethodId,
            'category_id'         => $dto->categoryId,
            'operation_origen_id' => $dto->operationOrigenId,
            'descripcion'         => $dto->descripcion,
            'fecha_operacion'     => $dto->fechaOperacion,
            'fecha_vencimiento'   => $dto->fechaVencimiento,
            'monto_original'      => $dto->montoOriginal,
            'moneda_original'     => $dto->monedaOriginal,
            'tipo_cambio'         => $dto->tipoCambio,
            'monto_pen'           => $montoPen,
            'es_diferida'         => $dto->esDiferida && $dto->numeroCuotas > 1,
            'numero_cuotas'       => $dto->numeroCuotas,
            'estado_deuda'        => $estadoDeuda,
            'monto_abonado'       => 0.00,
            'notas'               => $dto->notas,
            'comprobante_url'     => $dto->comprobanteUrl,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Generación de Cuotas (RN-08)
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Genera los N registros de installments para una operación diferida.
     * Soporta dos modos:
     *   a) Cuotas fijas: divide el monto equitativamente con ajuste en la última cuota.
     *   b) Cuotas personalizadas: usa el array cuotasPersonalizadas del DTO.
     */
    private function generarCuotas(Operation $operation, CreateOperationDTO $dto): void
    {
        $cuotas = !empty($dto->cuotasPersonalizadas)
            ? $this->calcularCuotasPersonalizadas($operation, $dto)
            : $this->calcularCuotasFijas($operation, $dto);

        // Inserción masiva para eficiencia
        Installment::insert($cuotas);
    }

    /**
     * Calcula cuotas de monto fijo con ajuste en la última para evitar diferencias de redondeo.
     * Las fechas de vencimiento se alinean al ciclo de facturación de la tarjeta
     * si el medio de pago es una tarjeta de crédito configurada; de lo contrario
     * se calculan mensualmente desde la fecha de operación (+1 mes por cuota).
     */
    private function calcularCuotasFijas(Operation $operation, CreateOperationDTO $dto): array
    {
        $n          = $dto->numeroCuotas;
        $montoPen   = $operation->monto_pen;
        $montoCuota = round($montoPen / $n, 2);

        // La última cuota absorbe el residuo de redondeo
        $montoUltima = round($montoPen - ($montoCuota * ($n - 1)), 2);

        $fechasVencimiento = $this->fechasVencimientoCuotas($operation, $dto);

        $cuotas    = [];
        $now       = now();

        for ($i = 1; $i <= $n; $i++) {
            $cuotas[] = [
                'operation_id'      => $operation->id,
                'numero_cuota'      => $i,
                'total_cuotas'      => $n,
                'monto_cuota'       => ($i === $n) ? $montoUltima : $montoCuota,
                'monto_abonado'     => 0.00,
                'fecha_vencimiento' => $fechasVencimiento[$i - 1],
                'fecha_pago'        => null,
                'estado'            => 'PENDIENTE',
                'notas'             => null,
                'created_at'        => $now,
                'updated_at'        => $now,
                'deleted_at'        => null,
            ];
        }

        return $cuotas;
    }

    /**
     * Determina las fechas de vencimiento de las cuotas de una operación diferida.
     *
     * Si el medio de pago es una tarjeta de crédito con ciclo de facturación
     * configurado (día de corte y día de pago), las cuotas se alinean a ese ciclo
     * (RN-07). En caso contrario se usan vencimientos mensuales desde la fecha
     * de operación: cuota i vence en fecha_operacion + i meses.
     *
     * @return array<string> Fechas Y-m-d, una por cuota (índice 0..N-1)
     */
    private function fechasVencimientoCuotas(Operation $operation, CreateOperationDTO $dto): array
    {
        $n         = $dto->numeroCuotas;
        $fechaBase = Carbon::parse($dto->fechaOperacion);

        $pm = PaymentMethod::find($operation->payment_method_id);

        if ($pm && $pm->tipo?->value === 'CREDITO' && $pm->dia_corte && $pm->dia_pago) {
            return CreditCardService::fechasVencimientoPorCiclo($pm, $fechaBase, $n);
        }

        $fechas = [];
        for ($i = 1; $i <= $n; $i++) {
            $fechas[] = $fechaBase->copy()->addMonths($i)->toDateString();
        }

        return $fechas;
    }

    /**
     * Convierte el array cuotasPersonalizadas del DTO en registros para la BD.
     */
    private function calcularCuotasPersonalizadas(Operation $operation, CreateOperationDTO $dto): array
    {
        $n    = $dto->numeroCuotas;
        $now  = now();
        return collect($dto->cuotasPersonalizadas)
            ->map(fn($cuota, $idx) => [
                'operation_id'      => $operation->id,
                'numero_cuota'      => $idx + 1,
                'total_cuotas'      => $n,
                'monto_cuota'       => round((float) $cuota['monto'], 2),
                'monto_abonado'     => 0.00,
                'fecha_vencimiento' => $cuota['fecha_vencimiento'],
                'fecha_pago'        => null,
                'estado'            => 'PENDIENTE',
                'notas'             => $cuota['notas'] ?? null,
                'created_at'        => $now,
                'updated_at'        => $now,
                'deleted_at'        => null,
            ])
            ->values()
            ->all();
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Ajuste de saldo de medio de pago
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Ajusta el saldo_actual del medio de pago al registrar una operación.
     *
     * Lógica:
     *   GASTO_PERSONAL / COMPRA_TERCERO / PAGO_TARJETA (en débito/efectivo) → resta
     *   INGRESO_PERSONAL / DEVOLUCION → suma
     *   PRESTAMO_OTORGADO → resta (dinero saliente)
     *   PRESTAMO_RECIBIDO → suma (dinero entrante)
     *   COMPRA_TERCERO en CREDITO → reduce línea disponible (monto_pen negativo en saldo)
     */
    private function ajustarSaldoMedioPago(Operation $operation): void
    {
        /** @var PaymentMethod $pm */
        $pm = PaymentMethod::lockForUpdate()->find($operation->payment_method_id);
        if (!$pm) {
            return;
        }

        $delta = match ($operation->tipo_operacion->value) {
            'GASTO_PERSONAL',
            'COMPRA_TERCERO',
            'PAGO_TARJETA'      => -$operation->monto_pen,  // salida de dinero
            'PRESTAMO_OTORGADO' => -$operation->monto_pen,  // dinero entregado
            'INGRESO_PERSONAL',
            'DEVOLUCION'        => $operation->monto_pen,   // entrada de dinero
            'PRESTAMO_RECIBIDO' => $operation->monto_pen,   // dinero recibido
            default             => 0,
        };

        $pm->saldo_actual = round($pm->saldo_actual + $delta, 2);
        $pm->save();
    }

    /**
     * Revierte el ajuste de saldo (para anulaciones).
     */
    private function revertirSaldoMedioPago(Operation $operation): void
    {
        /** @var PaymentMethod $pm */
        $pm = PaymentMethod::lockForUpdate()->find($operation->payment_method_id);
        if (!$pm) {
            return;
        }

        // Delta inverso al de ajustar
        $delta = match ($operation->tipo_operacion->value) {
            'GASTO_PERSONAL',
            'COMPRA_TERCERO',
            'PAGO_TARJETA'      => $operation->monto_pen,
            'PRESTAMO_OTORGADO' => $operation->monto_pen,
            'INGRESO_PERSONAL',
            'DEVOLUCION'        => -$operation->monto_pen,
            'PRESTAMO_RECIBIDO' => -$operation->monto_pen,
            default             => 0,
        };

        $pm->saldo_actual = round($pm->saldo_actual + $delta, 2);
        $pm->save();
    }
}
