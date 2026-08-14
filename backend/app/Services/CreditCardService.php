<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Operation;
use App\Models\PaymentMethod;
use App\Services\DTOs\ApplyPaymentDTO;
use App\Services\DTOs\CreateOperationDTO;
use App\Models\TipoOperacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CreditCardService — Servicio de Dominio para Tarjetas de Crédito.
 *
 * Implementa:
 *   RN-02 → Línea disponible = Línea Total - Consumos pendientes de pago.
 *   RN-07 → Estado de cuenta facturado del mes (corte).
 *   RF-05 → Proceso de "Pago de Tarjeta": libera línea y descuenta cuenta de débito.
 *
 * Dependencias:
 *   OperationService   → para registrar el PAGO_TARJETA como operación
 *   DebtPaymentService → para aplicar el abono a las cuotas/consumos de tarjeta
 */
class CreditCardService
{
    public function __construct(
        private readonly OperationService    $operationService,
        private readonly DebtPaymentService  $debtPaymentService,
    ) {}

    // ──────────────────────────────────────────────────────────────────────────────
    // Fechas de vencimiento de cuotas según ciclo de facturación (RN-07)
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Calcula las fechas de vencimiento de las cuotas de una operación diferida
     * alineadas al ciclo de facturación de la tarjeta de crédito.
     *
     * Regla de negocio:
     *  - Si la compra se realiza en o antes del día de corte del mes, entra en la
     *    factura que cierra ese mes; su primera cuota vence el día de pago del mes
     *    siguiente.
     *  - Si la compra se realiza después del corte, entra en la factura del mes
     *    siguiente; su primera cuota vence el día de pago del mes posterior.
     *  - Las cuotas restantes vencen el mismo día de pago en los meses sucesivos.
     *
     * Ejemplo: TV S/ 1,000 en 2 cuotas (corte día 15, pago día 5), compra el 10/08
     *   → Cuota 1 vence 05/09 (S/ 500), Cuota 2 vence 05/10 (S/ 500).
     *
     * @param  PaymentMethod $tarjeta         Tarjeta con dia_corte y dia_pago
     * @param  Carbon        $fechaOperacion  Fecha de la compra
     * @param  int           $numeroCuotas    Cantidad de cuotas (N)
     * @return array<string> Fechas Y-m-d, una por cuota (índice 0..N-1)
     */
    public static function fechasVencimientoPorCiclo(
        PaymentMethod $tarjeta,
        Carbon $fechaOperacion,
        int $numeroCuotas
    ): array {
        $diaCorte = (int) $tarjeta->dia_corte;
        $diaPago  = (int) $tarjeta->dia_pago;

        // Sin ciclo de facturación configurado: vencimiento mensual +1 mes por cuota.
        if ($diaCorte < 1 || $diaPago < 1) {
            $fechas = [];
            for ($i = 1; $i <= $numeroCuotas; $i++) {
                $fechas[] = $fechaOperacion->copy()->addMonths($i)->toDateString();
            }
            return $fechas;
        }

        // Fecha de corte del mes en que se realizó la compra
        $corteMes = self::diaDelMes($fechaOperacion, $diaCorte);

        // Mes de facturación que captura la compra:
        //   mismo mes si se compró en o antes del corte, mes siguiente si fue después.
        $mesFactura = $fechaOperacion->lte($corteMes)
            ? $fechaOperacion
            : $fechaOperacion->copy()->addMonthNoOverflow();

        // Primera cuota: día de pago del mes siguiente al de facturación.
        $primeraVencimiento = self::diaDelMes($mesFactura->copy()->addMonthNoOverflow(), $diaPago);

        $fechas = [];
        for ($i = 0; $i < $numeroCuotas; $i++) {
            $fechas[] = self::diaDelMes($primeraVencimiento->copy()->addMonths($i), $diaPago)->toDateString();
        }

        return $fechas;
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // RN-02: Línea Disponible
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Calcula la línea de crédito disponible de una tarjeta en tiempo real.
     *
     * RN-02: Línea Disponible = Línea Total − Σ(consumos pendientes de pago en PEN)
     *
     * "Consumos pendientes" = operaciones COMPRA_TERCERO/GASTO_PERSONAL en esa tarjeta
     * cuyo estado_deuda es PENDIENTE o PARCIAL + cuotas vigentes de diferidos.
     *
     * @return array{
     *     linea_total: float,
     *     consumos_pendientes: float,
     *     linea_disponible: float,
     *     moneda: string,
     * }
     * @throws BusinessRuleException Si el payment_method no es de tipo CREDITO
     */
    public function calcularLineaDisponible(int $paymentMethodId): array
    {
        $tarjeta = $this->obtenerTarjeta($paymentMethodId);

        if ($tarjeta->linea_total === null) {
            throw new BusinessRuleException(
                'La tarjeta no tiene configurada una línea de crédito total.',
                rule: 'RN-02',
                context: ['payment_method_id' => $paymentMethodId]
            );
        }

        // Consumos activos no diferidos
        $consumosDirectos = Operation::where('payment_method_id', $paymentMethodId)
            ->whereIn('tipo_operacion', ['GASTO_PERSONAL', 'COMPRA_TERCERO'])
            ->whereIn('estado_deuda', ['PENDIENTE', 'PARCIAL'])
            ->where('es_diferida', false)
            ->sum('monto_saldo'); // columna generada = monto_pen - monto_abonado

        // Cuotas diferidas pendientes (suma de saldos de cuotas)
        $consumosDiferidos = DB::table('installments as i')
            ->join('operations as o', 'o.id', '=', 'i.operation_id')
            ->where('o.payment_method_id', $paymentMethodId)
            ->whereIn('o.tipo_operacion', ['GASTO_PERSONAL', 'COMPRA_TERCERO'])
            ->whereIn('i.estado', ['PENDIENTE', 'PARCIAL'])
            ->whereNull('i.deleted_at')
            ->whereNull('o.deleted_at')
            ->sum(DB::raw('i.monto_cuota - i.monto_abonado'));

        $consumosPendientes = round((float) $consumosDirectos + (float) $consumosDiferidos, 2);
        $lineaDisponible    = round((float) $tarjeta->linea_total - $consumosPendientes, 2);

        return [
            'linea_total'         => (float) $tarjeta->linea_total,
            'consumos_pendientes' => $consumosPendientes,
            'linea_disponible'    => max(0, $lineaDisponible),
            'moneda'              => $tarjeta->moneda,
            'payment_method_id'   => $tarjeta->id,
            'nombre_tarjeta'      => $tarjeta->nombre,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // RN-07: Estado de Cuenta Facturado del Mes (Corte)
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Calcula el estado de cuenta facturado para el ciclo de corte actual o uno específico.
     *
     * RN-07: Facturado del Mes =
     *   ∑ consumos en 1 cuota (pagados en este ciclo de corte)
     *   + ∑ cuota del mes correspondiente de operaciones diferidas
     *   + comisión de mantenimiento de la tarjeta
     *
     * Un ciclo de corte va desde el día_corte del mes anterior hasta el día_corte del mes actual.
     *
     * @param  int         $paymentMethodId
     * @param  Carbon|null $fechaCorteRef   Fecha de referencia para calcular el ciclo (null = hoy)
     * @return array{
     *     ciclo_desde: string,
     *     ciclo_hasta: string,
     *     fecha_pago_limite: string,
     *     consumos_contado: float,
     *     consumos_diferidos_cuota: float,
     *     comision_mantenimiento: float,
     *     total_facturado: float,
     *     detalle_operaciones: array,
     *     detalle_cuotas: array,
     * }
     */
    public function calcularEstadoDeCuenta(int $paymentMethodId, ?Carbon $fechaCorteRef = null): array
    {
        $tarjeta = $this->obtenerTarjeta($paymentMethodId);
        $this->validarConfiguracionCorte($tarjeta);

        $hoy       = $fechaCorteRef ?? Carbon::today();
        $diaCorte  = (int) $tarjeta->dia_corte;
        $diaPago   = (int) $tarjeta->dia_pago;

        // ── Calcular fechas del ciclo ─────────────────────────────────────────────
        [$cicloDesde, $cicloHasta] = $this->calcularCicloDeFechas($hoy, $diaCorte);
        $fechaPagoLimite = $this->calcularFechaPago($cicloHasta, $diaPago);

        // ── Consumos en 1 cuota (no diferidos) en el período de corte ────────────
        $consumosContado = Operation::where('payment_method_id', $paymentMethodId)
            ->whereIn('tipo_operacion', ['GASTO_PERSONAL', 'COMPRA_TERCERO'])
            ->where('es_diferida', false)
            ->whereBetween('fecha_operacion', [$cicloDesde->toDateString(), $cicloHasta->toDateString()])
            ->whereNull('deleted_at')
            ->get(['id', 'descripcion', 'fecha_operacion', 'monto_pen', 'estado_deuda']);

        // ── Cuotas de diferidos que vencen en este período ────────────────────────
        $cuotasDelMes = DB::table('installments as i')
            ->join('operations as o', 'o.id', '=', 'i.operation_id')
            ->select([
                'i.id',
                'i.numero_cuota',
                'i.total_cuotas',
                'i.monto_cuota',
                'i.monto_abonado',
                'i.fecha_vencimiento',
                'i.estado',
                'o.descripcion',
                'o.id as operation_id',
            ])
            ->where('o.payment_method_id', $paymentMethodId)
            ->whereIn('o.tipo_operacion', ['GASTO_PERSONAL', 'COMPRA_TERCERO'])
            ->where('o.es_diferida', true)
            ->whereBetween('i.fecha_vencimiento', [$cicloDesde->toDateString(), $cicloHasta->toDateString()])
            ->whereNull('i.deleted_at')
            ->whereNull('o.deleted_at')
            ->get()
            ->map(function ($cuota) {
                $cuota = (array) $cuota;
                $cuota['monto_saldo'] = round((float) $cuota['monto_cuota'] - (float) $cuota['monto_abonado'], 2);
                return $cuota;
            })
            ->values();

        // ── Totales ───────────────────────────────────────────────────────────────
        $totalContado    = round($consumosContado->sum('monto_pen'), 2);
        $totalDiferidos  = round($cuotasDelMes->sum('monto_cuota'), 2);
        $comision        = (float) $tarjeta->comision_mantenimiento;
        $totalFacturado  = round($totalContado + $totalDiferidos + $comision, 2);

        return [
            'ciclo_desde'             => $cicloDesde->toDateString(),
            'ciclo_hasta'             => $cicloHasta->toDateString(),
            'fecha_pago_limite'       => $fechaPagoLimite->toDateString(),
            'consumos_contado'        => $totalContado,
            'consumos_diferidos_cuota'=> $totalDiferidos,
            'comision_mantenimiento'  => $comision,
            'total_facturado'         => $totalFacturado,
            'moneda'                  => $tarjeta->moneda,
            'detalle_operaciones'     => $consumosContado->toArray(),
            'detalle_cuotas'          => $cuotasDelMes->toArray(),
            'cuotas_a_pagar'          => $cuotasDelMes
                ->filter(fn($cuota) => in_array($cuota['estado'], ['PENDIENTE', 'PARCIAL']))
                ->values()
                ->toArray(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // RF-05: Proceso de Pago de Tarjeta
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Procesa el pago de un estado de cuenta de tarjeta de crédito.
     *
     * RF-05 — Flujo:
     *   1. Registrar la operación PAGO_TARJETA (cargo a la cuenta de débito/efectivo fuente).
     *   2. Aplicar el abono a las deudas de la tarjeta (libera línea de crédito).
     *   3. Actualizar saldo_actual de la tarjeta de crédito.
     *
     * @param  int    $tarjetaCreditoId    ID del payment_method de tarjeta de crédito
     * @param  int    $cuentaFuenteId      ID del payment_method débito/efectivo que paga
     * @param  float  $montoOriginal       Monto a pagar
     * @param  string $monedaOriginal      PEN | USD
     * @param  float  $tipoCambio          Tipo de cambio
     * @param  string $fechaPago           Y-m-d
     * @param  string $descripcion         Descripción del pago
     * @param  array  $asignacionesManual  Si está vacío, se aplica FIFO sobre las deudas de la tarjeta
     *
     * @return array{operation: Operation, payment_result: \App\Services\DTOs\PaymentResultDTO}
     * @throws BusinessRuleException
     */
    public function pagarTarjeta(
        int    $tarjetaCreditoId,
        int    $cuentaFuenteId,
        float  $montoOriginal,
        string $monedaOriginal = 'PEN',
        float  $tipoCambio = 1.0,
        string $fechaPago = '',
        string $descripcion = 'Pago de tarjeta de crédito',
        array  $asignacionesManual = [],
    ): array {
        return DB::transaction(function () use (
            $tarjetaCreditoId, $cuentaFuenteId, $montoOriginal,
            $monedaOriginal, $tipoCambio, $fechaPago, $descripcion, $asignacionesManual
        ) {
            $tarjeta     = $this->obtenerTarjeta($tarjetaCreditoId);
            $cuentaFuente = PaymentMethod::findOrFail($cuentaFuenteId);

            $montoPen = round($montoOriginal * $tipoCambio, 2);

            // ── Validar que la cuenta fuente tiene saldo suficiente ───────────────
            if ($cuentaFuente->saldo_actual < $montoPen) {
                throw new BusinessRuleException(
                    sprintf(
                        'Saldo insuficiente en "%s". Disponible: S/ %.2f — Requerido: S/ %.2f.',
                        $cuentaFuente->nombre,
                        $cuentaFuente->saldo_actual,
                        $montoPen
                    ),
                    rule: 'RN-02',
                    context: [
                        'cuenta_fuente_id' => $cuentaFuenteId,
                        'saldo_actual'     => $cuentaFuente->saldo_actual,
                        'monto_requerido'  => $montoPen,
                    ]
                );
            }

            // ── 1. Registrar PAGO_TARJETA como operación (débita la cuenta fuente) ─
            $operationDto = new CreateOperationDTO(
                tipoOperacion:   TipoOperacion::PagoTarjeta,
                contactId:       null,
                paymentMethodId: $cuentaFuenteId,    // La cuenta que paga (débito/efectivo)
                categoryId:      null,
                descripcion:     $descripcion,
                fechaOperacion:  $fechaPago ?: now()->toDateString(),
                fechaVencimiento:null,
                montoOriginal:   $montoOriginal,
                monedaOriginal:  $monedaOriginal,
                tipoCambio:      $tipoCambio,
                esDiferida:      false,
                numeroCuotas:    1,
            );

            $operation = $this->operationService->register($operationDto);

            // ── 2. Aplicar abono a las deudas de la tarjeta de crédito ────────────
            $modoAsignacion  = empty($asignacionesManual) ? 'auto' : 'manual';

            // En modo auto: buscar deudas cuyo payment_method_id sea la tarjeta
            // Usamos DebtPaymentService sin contactId cuando es pago de tarjeta propio
            $paymentDto = new ApplyPaymentDTO(
                montoOriginal:      $montoOriginal,
                monedaOriginal:     $monedaOriginal,
                tipoCambio:         $tipoCambio,
                paymentMethodId:    $cuentaFuenteId, // el medio que origina el abono
                fechaPago:          $fechaPago ?: now()->toDateString(),
                referencia:         "PAGO_TARJETA-{$operation->id}",
                notas:              $descripcion,
                modoAsignacion:     $modoAsignacion,
                asignacionesManual: $asignacionesManual,
                contactId:          null,
                tipoOperacion:      null,
            );

            // Si es FIFO sin contacto, buscamos las deudas de la tarjeta directamente
            if ($modoAsignacion === 'auto') {
                $resultado = $this->aplicarAbonoTarjetaFIFO($tarjeta, $paymentDto, $montoPen);
            } else {
                $resultado = $this->debtPaymentService->apply($paymentDto);
            }

            // ── 3. El saldo_actual de la tarjeta de crédito se actualiza al ────────
            //        cancelar sus deudas individuales (recalcularEstado en Operation)
            //        El ajuste del saldo de la cuenta FUENTE ya lo hace OperationService.

            return [
                'operation'      => $operation,
                'payment_result' => $resultado,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * FIFO especializado para tarjeta: no filtra por contacto sino por payment_method_id.
     */
    private function aplicarAbonoTarjetaFIFO(
        PaymentMethod $tarjeta,
        ApplyPaymentDTO $dto,
        float $montoPen
    ): \App\Services\DTOs\PaymentResultDTO {
        // Crear el Payment manualmente (sin contactId)
        return DB::transaction(function () use ($tarjeta, $dto, $montoPen) {
            $payment = \App\Models\Payment::create([
                'payment_method_id' => $dto->paymentMethodId,
                'monto_original'    => $dto->montoOriginal,
                'moneda_original'   => $dto->monedaOriginal,
                'tipo_cambio'       => $dto->tipoCambio,
                'monto_pen'         => $montoPen,
                'fecha_pago'        => $dto->fechaPagoResuelta(),
                'referencia'        => $dto->referencia,
                'notas'             => $dto->notas,
                'comprobante_url'   => null,
            ]);

            // Obtener deudas activas de esta tarjeta de crédito en FIFO
            $operaciones = Operation::where('payment_method_id', $tarjeta->id)
                ->whereIn('tipo_operacion', ['GASTO_PERSONAL', 'COMPRA_TERCERO'])
                ->whereIn('estado_deuda', ['PENDIENTE', 'PARCIAL'])
                ->orderBy('fecha_operacion', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Reutilizar lógica de distribución FIFO interna a través de reflection
            // (accedemos al método privado del DebtPaymentService por composición)
            $lineas = $this->distribuirFIFOTarjeta($operaciones, $montoPen);

            // Invocar persistencia a través del servicio de pagos (herencia de lógica)
            // Usamos la API pública modificando el payment que ya creamos
            $operacionesAfectadas = $this->persistirLineasConPayment($payment, $lineas);

            $totalAsignado = round(collect($lineas)->sum('monto'), 2);

            return new \App\Services\DTOs\PaymentResultDTO(
                payment:              $payment,
                operacionesAfectadas: $operacionesAfectadas,
                totalAsignado:        $totalAsignado,
                saldoSinAsignar:      round($montoPen - $totalAsignado, 2),
                modoAsignacion:       'auto-tarjeta',
            );
        });
    }

    /**
     * Distribuye el monto entre las operaciones de la tarjeta en orden FIFO.
     *
     * @return array<array{operation: Operation, installment: ?Installment, monto: float}>
     */
    private function distribuirFIFOTarjeta(Collection $operaciones, float $montoDisponible): array
    {
        $lineas = [];

        foreach ($operaciones as $operation) {
            if ($montoDisponible <= 0) {
                break;
            }

            if ($operation->es_diferida) {
                // Cuotas diferidas FIFO
                $cuotas = $operation->installmentsPendientes()->lockForUpdate()->get();
                foreach ($cuotas as $cuota) {
                    if ($montoDisponible <= 0) {
                        break;
                    }
                    $saldo = round((float) $cuota->monto_cuota - (float) $cuota->monto_abonado, 2);
                    if ($saldo <= 0) continue;
                    $monto           = min($montoDisponible, $saldo);
                    $montoDisponible = round($montoDisponible - $monto, 2);
                    $lineas[]        = ['operation' => $operation, 'installment' => $cuota, 'monto' => $monto];
                }
            } else {
                $saldo = round((float) $operation->monto_pen - (float) $operation->monto_abonado, 2);
                if ($saldo <= 0) continue;
                $monto           = min($montoDisponible, $saldo);
                $montoDisponible = round($montoDisponible - $monto, 2);
                $lineas[]        = ['operation' => $operation, 'installment' => null, 'monto' => $monto];
            }
        }

        return $lineas;
    }

    /**
     * Persiste las líneas de asignación para el pago de tarjeta.
     */
    private function persistirLineasConPayment(\App\Models\Payment $payment, array $lineas): array
    {
        $operacionesMap = [];

        foreach ($lineas as $linea) {
            $operation   = $linea['operation'];
            $installment = $linea['installment'];
            $monto       = $linea['monto'];

            \App\Models\DebtAllocation::create([
                'payment_id'     => $payment->id,
                'operation_id'   => $operation->id,
                'installment_id' => $installment?->id,
                'monto_asignado' => $monto,
            ]);

            if ($installment) {
                $installment->monto_abonado = round($installment->monto_abonado + $monto, 2);
                $installment->estado = match (true) {
                    $installment->monto_abonado >= $installment->monto_cuota - 0.01 => 'PAGADA',
                    $installment->monto_abonado > 0 => 'PARCIAL',
                    default => 'PENDIENTE',
                };
                if ($installment->estado === 'PAGADA' && !$installment->fecha_pago) {
                    $installment->fecha_pago = now()->toDateString();
                }
                $installment->save();
            }

            $opId = $operation->id;
            if (!isset($operacionesMap[$opId])) {
                $operacionesMap[$opId] = ['operation' => $operation, 'monto_asignado' => 0.0];
            }
            $operacionesMap[$opId]['monto_asignado'] += $monto;
        }

        foreach ($operacionesMap as &$item) {
            $item['operation']->recalcularEstado();
            $item['operation']->refresh();
        }

        return array_values($operacionesMap);
    }

    /**
     * Obtiene y valida que el payment_method sea una tarjeta de crédito.
     */
    private function obtenerTarjeta(int $paymentMethodId): PaymentMethod
    {
        $tarjeta = PaymentMethod::findOrFail($paymentMethodId);

        if ($tarjeta->tipo?->value !== 'CREDITO') {
            throw new BusinessRuleException(
                "El medio de pago '{$tarjeta->nombre}' (ID: {$paymentMethodId}) no es una tarjeta de crédito.",
                rule: 'RN-02',
                context: ['tipo' => $tarjeta->tipo?->value, 'payment_method_id' => $paymentMethodId]
            );
        }

        return $tarjeta;
    }

    /**
     * Valida que la tarjeta tiene los días de corte y pago configurados.
     */
    private function validarConfiguracionCorte(PaymentMethod $tarjeta): void
    {
        if (!$tarjeta->dia_corte || !$tarjeta->dia_pago) {
            throw new BusinessRuleException(
                "La tarjeta '{$tarjeta->nombre}' no tiene configurados los días de corte y pago.",
                rule: 'RN-07',
                context: [
                    'payment_method_id' => $tarjeta->id,
                    'dia_corte'         => $tarjeta->dia_corte,
                    'dia_pago'          => $tarjeta->dia_pago,
                ]
            );
        }
    }

    /**
     * Calcula el rango del ciclo de facturación basado en el día de corte.
     * Si hoy es después del día de corte, el ciclo va del corte pasado al corte actual.
     * Si hoy es antes, va del corte del mes anterior al del mes actual (pendiente de cerrar).
     *
     * @return array{Carbon, Carbon}  [cicloDesde, cicloHasta]
     */
    private function calcularCicloDeFechas(Carbon $hoy, int $diaCorte): array
    {
        try {
            $corteActual = Carbon::createFromDate($hoy->year, $hoy->month, $diaCorte);
        } catch (\Exception) {
            // Si el día no existe en el mes (ej. día 31 en febrero), usar el último día
            $corteActual = $hoy->copy()->endOfMonth();
        }

        if ($hoy->gte($corteActual)) {
            // Estamos en el período: corte anterior → corte actual
            $cicloHasta  = $corteActual->copy();
            $cicloDesde  = $corteActual->copy()->subMonthNoOverflow()->addDay();
        } else {
            // Aún no hemos llegado al corte; ciclo: corte del mes pasado → corte este mes
            $cicloHasta  = $corteActual->copy();
            $cicloDesde  = $corteActual->copy()->subMonthNoOverflow()->addDay();
        }

        return [$cicloDesde, $cicloHasta];
    }

    /**
     * Calcula la fecha límite de pago del estado de cuenta.
     * Normalmente es dia_pago del mes siguiente al corte.
     */
    private function calcularFechaPago(Carbon $cicloHasta, int $diaPago): Carbon
    {
        $mesFactura = $cicloHasta->copy()->addMonthNoOverflow();
        return self::diaDelMes($mesFactura, $diaPago);
    }

    /**
     * Devuelve un Carbon con el día indicado del mes de $base.
     * Si el día no existe en ese mes (ej. 31 en febrero), usa el último día del mes.
     * El clamping es explícito porque Carbon normaliza fechas inválidas en lugar
     * de lanzar excepción.
     */
    private static function diaDelMes(Carbon $base, int $dia): Carbon
    {
        $diaReal = min($dia, $base->copy()->endOfMonth()->day);
        return Carbon::createFromDate($base->year, $base->month, $diaReal);
    }
}
