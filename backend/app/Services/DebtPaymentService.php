<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\OverpaymentException;
use App\Models\DebtAllocation;
use App\Models\Installment;
use App\Models\Operation;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\DTOs\ApplyPaymentDTO;
use App\Services\DTOs\PaymentResultDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DebtPaymentService — Motor de Abonos del Sistema Control-G.
 *
 * Implementa RF-04.1, RF-04.2 y las siguientes reglas de negocio:
 *   RN-06 → Anti-sobrepago: monto abonado ≤ saldo pendiente de la deuda/cuota.
 *   RN-04 → Todo registro de abono es inmutable (soft delete para cancelar).
 *   FIFO  → En modo automático, aplica el abono primero a las deudas más antiguas.
 *
 * Flujo principal (apply):
 *   1. Crear cabecera Payment.
 *   2. Resolver operaciones/cuotas destino (FIFO o manual).
 *   3. Distribuir el monto y crear DebtAllocation por cada línea.
 *   4. Recalcular estado_deuda de cada Operation afectada.
 *   5. Actualizar saldo del medio de pago de destino (si aplica).
 */
class DebtPaymentService
{
    // ──────────────────────────────────────────────────────────────────────────────
    // Punto de entrada principal
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Aplica un abono a una o varias deudas.
     *
     * @param  ApplyPaymentDTO  $dto  Datos del abono validados desde el FormRequest
     * @return PaymentResultDTO       Resultado completo de la operación
     * @throws BusinessRuleException
     * @throws OverpaymentException
     */
    public function apply(ApplyPaymentDTO $dto): PaymentResultDTO
    {
        return DB::transaction(function () use ($dto) {
            $montoPen = $dto->montoPen();

            // ── 1. Validar monto mínimo ──────────────────────────────────────────
            if ($montoPen <= 0) {
                throw new BusinessRuleException(
                    'El monto del abono debe ser mayor a S/ 0.00.',
                    rule: 'RN-06',
                    context: ['monto_pen' => $montoPen]
                );
            }

            // ── 2. Crear cabecera del pago ───────────────────────────────────────
            $payment = $this->crearCabeceraPayment($dto, $montoPen);

            // ── 3. Resolver líneas de asignación ────────────────────────────────
            $lineas = match ($dto->modoAsignacion) {
                'manual' => $this->resolverLineasManual($dto, $montoPen),
                'auto'   => $this->resolverLineasFIFO($dto, $montoPen),
                default  => throw new BusinessRuleException(
                    "Modo de asignación desconocido: '{$dto->modoAsignacion}'. Use 'auto' o 'manual'.",
                    rule: 'RN-06'
                ),
            };

            // ── 4. Persistir asignaciones y actualizar estados ───────────────────
            $operacionesAfectadas = $this->persistirAsignaciones($payment, $lineas);

            // ── 5. Ajustar saldo del medio de pago fuente ────────────────────────
            if ($payment->payment_method_id) {
                $this->ajustarSaldoMedioPago($payment);
            }

            $totalAsignado = round(collect($lineas)->sum('monto'), 2);
            $saldoSinAsignar = round($montoPen - $totalAsignado, 2);

            if ($saldoSinAsignar > 0) {
                throw new OverpaymentException(
                    montoIntentado: $montoPen,
                    saldoPendiente: $totalAsignado
                );
            }

            return new PaymentResultDTO(
                payment:              $payment,
                operacionesAfectadas: $operacionesAfectadas,
                totalAsignado:        $totalAsignado,
                saldoSinAsignar:      $saldoSinAsignar,
                modoAsignacion:       $dto->modoAsignacion,
            );
        });
    }

    /**
     * Cancela un pago (soft delete en payment y sus allocations).
     * Revierte los estados de deuda de las operaciones afectadas.
     *
     * RN-04: No elimina físicamente ningún registro.
     */
    public function cancelar(Payment $payment, string $motivo = ''): void
    {
        DB::transaction(function () use ($payment, $motivo) {
            // Recopilar operaciones afectadas antes de eliminar
            $operacionIds = $payment->debtAllocations()
                                    ->whereNull('deleted_at')
                                    ->pluck('operation_id')
                                    ->unique();

            // Soft delete de las asignaciones
            $payment->debtAllocations()->each(fn($a) => $a->delete());

            // Soft delete de la cabecera
            $payment->update([
                'notas' => trim(($payment->notas ?? '') . "\n[CANCELADO] " . $motivo),
            ]);
            $payment->delete();

            // Revertir saldo del medio de pago
            if ($payment->payment_method_id) {
                $this->revertirSaldoMedioPago($payment);
            }

            // Recalcular estado de cada operación afectada
            Operation::whereIn('id', $operacionIds)
                     ->each(fn(Operation $op) => $op->recalcularEstado());

            // Recalcular cuotas afectadas por este pago
            $installmentIds = $payment->debtAllocations()
                                      ->withTrashed()
                                      ->whereNotNull('installment_id')
                                      ->pluck('installment_id')
                                      ->unique();

            Installment::whereIn('id', $installmentIds)
                       ->each(fn(Installment $inst) => $this->recalcularEstadoCuota($inst));
        });
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Creación de Cabecera Payment
    // ──────────────────────────────────────────────────────────────────────────────

    private function crearCabeceraPayment(ApplyPaymentDTO $dto, float $montoPen): Payment
    {
        return Payment::create([
            'payment_method_id' => $dto->paymentMethodId,
            'monto_original'    => $dto->montoOriginal,
            'moneda_original'   => $dto->monedaOriginal,
            'tipo_cambio'       => $dto->tipoCambio,
            'monto_pen'         => $montoPen,
            'fecha_pago'        => $dto->fechaPagoResuelta(),
            'referencia'        => $dto->referencia,
            'notas'             => $dto->notas,
            'comprobante_url'   => $dto->comprobanteUrl,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Modo FIFO — Asignación Automática
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Algoritmo FIFO: distribuye el monto_pen entre las deudas pendientes
     * del contacto, ordenadas de más antigua a más reciente.
     *
     * RF-04.1: La deuda más antigua se cancela primero.
     *
     * @return array<array{operation: Operation, installment: ?Installment, monto: float}>
     * @throws BusinessRuleException Si no hay deudas activas para el contacto
     */
    private function resolverLineasFIFO(ApplyPaymentDTO $dto, float $montoDisponible): array
    {
        if (!$dto->contactId) {
            throw new BusinessRuleException(
                'El modo de asignación automática (FIFO) requiere un contactId.',
                rule: 'RN-06'
            );
        }

        // Obtener todas las deudas activas del contacto, ordenadas FIFO
        $operaciones = $this->obtenerDeudasActivasFIFO($dto->contactId, $dto->tipoOperacion);

        if ($operaciones->isEmpty()) {
            throw new BusinessRuleException(
                "El contacto (ID: {$dto->contactId}) no tiene deudas pendientes activas.",
                rule: 'RN-06',
                context: ['contact_id' => $dto->contactId]
            );
        }

        return $this->distribuirFIFO($operaciones, $montoDisponible);
    }

    /**
     * Obtiene deudas activas del contacto en orden FIFO (por fecha_operacion ASC).
     */
    private function obtenerDeudasActivasFIFO(int $contactId, ?string $tipoFiltro): Collection
    {
        return Operation::where('contact_id', $contactId)
            ->whereIn('estado_deuda', ['PENDIENTE', 'PARCIAL'])
            ->when($tipoFiltro, fn($q) => $q->where('tipo_operacion', $tipoFiltro))
            ->orderBy('fecha_operacion', 'asc')
            ->orderBy('id', 'asc')   // desempate por ID para ordenamiento estable
            ->lockForUpdate()        // Lock pesimista para evitar condiciones de carrera
            ->get();
    }

    /**
     * Distribuye el monto disponible entre operaciones en orden FIFO.
     * Soporte para operaciones con y sin cuotas.
     *
     * @return array<array{operation: Operation, installment: ?Installment, monto: float}>
     */
    private function distribuirFIFO(Collection $operaciones, float $montoDisponible): array
    {
        $lineas = [];

        foreach ($operaciones as $operation) {
            if ($montoDisponible <= 0) {
                break;
            }

            if ($operation->es_diferida) {
                // Para diferidas: abonar cuota a cuota en orden
                $cuotasLineas = $this->distribuirEnCuotasFIFO($operation, $montoDisponible);
                $montoDisponible -= collect($cuotasLineas)->sum('monto');
                $lineas = array_merge($lineas, $cuotasLineas);
            } else {
                // Para operaciones sin cuotas: abonar directamente
                $saldoOp = round((float) $operation->monto_pen - (float) $operation->monto_abonado, 2);

                if ($saldoOp <= 0) {
                    continue;
                }

                $montoAbonar     = min($montoDisponible, $saldoOp);
                $montoDisponible = round($montoDisponible - $montoAbonar, 2);

                $lineas[] = [
                    'operation'   => $operation,
                    'installment' => null,
                    'monto'       => $montoAbonar,
                ];
            }
        }

        return $lineas;
    }

    /**
     * Distribuye el monto en cuotas de una operación diferida, en orden FIFO por numero_cuota.
     */
    private function distribuirEnCuotasFIFO(Operation $operation, float $montoDisponible): array
    {
        $lineas = [];
        $cuotas = $operation->installmentsPendientes()->lockForUpdate()->get();

        foreach ($cuotas as $cuota) {
            if ($montoDisponible <= 0) {
                break;
            }

            $saldoCuota  = round((float) $cuota->monto_cuota - (float) $cuota->monto_abonado, 2);
            if ($saldoCuota <= 0) {
                continue;
            }

            $montoAbonar     = min($montoDisponible, $saldoCuota);
            $montoDisponible = round($montoDisponible - $montoAbonar, 2);

            $lineas[] = [
                'operation'   => $operation,
                'installment' => $cuota,
                'monto'       => $montoAbonar,
            ];
        }

        return $lineas;
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Modo Manual — Asignación Explícita
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Resuelve y valida las asignaciones manuales proporcionadas en el DTO.
     * RF-04.2
     *
     * @return array<array{operation: Operation, installment: ?Installment, monto: float}>
     * @throws BusinessRuleException | OverpaymentException
     */
    private function resolverLineasManual(ApplyPaymentDTO $dto, float $montoPen): array
    {
        if (empty($dto->asignacionesManual)) {
            throw new BusinessRuleException(
                'El modo manual requiere al menos una asignación en asignacionesManual.',
                rule: 'RN-06'
            );
        }

        $sumaAsignaciones = round(collect($dto->asignacionesManual)->sum('monto'), 2);

        // Validar que las asignaciones no superen el monto total del abono
        if ($sumaAsignaciones > $montoPen + 0.01) {
            throw new BusinessRuleException(
                sprintf(
                    'La suma de asignaciones manuales (S/ %.2f) supera el monto del abono (S/ %.2f).',
                    $sumaAsignaciones,
                    $montoPen
                ),
                rule: 'RN-06',
                context: [
                    'suma_asignaciones' => $sumaAsignaciones,
                    'monto_abono'       => $montoPen,
                ]
            );
        }

        $lineas = [];

        foreach ($dto->asignacionesManual as $asignacion) {
            $operation = Operation::lockForUpdate()->findOrFail((int) $asignacion['operation_id']);
            $monto     = round((float) $asignacion['monto'], 2);

            if (!empty($asignacion['installment_id'])) {
                // ── Asignación a cuota específica ────────────────────────────────
                /** @var Installment $cuota */
                $cuota = Installment::lockForUpdate()->findOrFail((int) $asignacion['installment_id']);

                // Validar que la cuota pertenece a la operación indicada
                if ($cuota->operation_id !== $operation->id) {
                    throw new BusinessRuleException(
                        "La cuota ID {$cuota->id} no pertenece a la operación ID {$operation->id}.",
                        rule: 'RN-06'
                    );
                }

                $saldoCuota = round((float) $cuota->monto_cuota - (float) $cuota->monto_abonado, 2);

                // RN-06: Anti-sobrepago en cuota
                if ($monto > $saldoCuota + 0.01) {
                    throw new OverpaymentException($monto, $saldoCuota, "cuota #{$cuota->numero_cuota}");
                }

                $lineas[] = [
                    'operation'   => $operation,
                    'installment' => $cuota,
                    'monto'       => $monto,
                ];
            } else {
                // ── Asignación directa a operación (sin cuotas) ──────────────────
                $saldoOp = round((float) $operation->monto_pen - (float) $operation->monto_abonado, 2);

                // RN-06: Anti-sobrepago en operación
                if ($monto > $saldoOp + 0.01) {
                    throw new OverpaymentException($monto, $saldoOp, "operación ID {$operation->id}");
                }

                $lineas[] = [
                    'operation'   => $operation,
                    'installment' => null,
                    'monto'       => $monto,
                ];
            }
        }

        return $lineas;
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Persistencia y Recálculo de Estados
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Crea los DebtAllocation, actualiza monto_abonado de cuotas y recalcula
     * el estado_deuda de cada operación afectada.
     *
     * @param  array $lineas  [{operation, installment, monto}]
     * @return array<int, array{operation: Operation, monto_asignado: float}>
     */
    private function persistirAsignaciones(Payment $payment, array $lineas): array
    {
        $operacionesMap = [];
        $now = now();

        foreach ($lineas as $linea) {
            /** @var Operation   $operation   */
            /** @var ?Installment $installment */
            $operation   = $linea['operation'];
            $installment = $linea['installment'];
            $monto       = $linea['monto'];

            // 1. Crear la línea de asignación
            DebtAllocation::create([
                'payment_id'     => $payment->id,
                'operation_id'   => $operation->id,
                'installment_id' => $installment?->id,
                'monto_asignado' => $monto,
                'notas'          => null,
            ]);

            // 2. Actualizar monto_abonado de la cuota (si aplica)
            if ($installment) {
                $installment->monto_abonado = round($installment->monto_abonado + $monto, 2);
                $this->recalcularEstadoCuota($installment);
            }

            // 3. Acumular por operación para recalcular su estado una sola vez
            $opId = $operation->id;
            if (!isset($operacionesMap[$opId])) {
                $operacionesMap[$opId] = ['operation' => $operation, 'monto_asignado' => 0.0];
            }
            $operacionesMap[$opId]['monto_asignado'] = round(
                $operacionesMap[$opId]['monto_asignado'] + $monto, 2
            );
        }

        // 4. Recalcular estado_deuda de cada operación única afectada
        foreach ($operacionesMap as &$item) {
            $item['operation']->recalcularEstado();
            $item['operation']->refresh();
        }

        return array_values($operacionesMap);
    }

    /**
     * Recalcula el estado de una cuota a partir de su monto_abonado.
     */
    private function recalcularEstadoCuota(Installment $installment): void
    {
        $installment->estado = match (true) {
            $installment->monto_abonado <= 0                              => 'PENDIENTE',
            $installment->monto_abonado >= $installment->monto_cuota - 0.01 => 'PAGADA',
            default                                                       => 'PARCIAL',
        };

        if ($installment->estado === 'PAGADA' && !$installment->fecha_pago) {
            $installment->fecha_pago = now()->toDateString();
        }

        $installment->save();
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Ajuste de saldo del medio de pago fuente del abono
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Al abonar, el dinero sale del medio de pago fuente del payment.
     * (No del medio de pago de la operación original.)
     */
    private function ajustarSaldoMedioPago(Payment $payment): void
    {
        /** @var PaymentMethod $pm */
        $pm = PaymentMethod::lockForUpdate()->find($payment->payment_method_id);
        if (!$pm) {
            return;
        }

        // El abono sale de la cuenta del usuario → resta saldo
        $pm->saldo_actual = round($pm->saldo_actual - $payment->monto_pen, 2);
        $pm->save();
    }

    private function revertirSaldoMedioPago(Payment $payment): void
    {
        /** @var PaymentMethod $pm */
        $pm = PaymentMethod::lockForUpdate()->find($payment->payment_method_id);
        if (!$pm) {
            return;
        }

        // Revertir: devolver el monto al saldo
        $pm->saldo_actual = round($pm->saldo_actual + $payment->monto_pen, 2);
        $pm->save();
    }
}
