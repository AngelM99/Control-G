<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Operation;
use App\Models\PaymentMethod;
use App\Services\CreditCardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CreditCardService $creditCardService
    ) {}

    /**
     * RF-05.1: API de KPIs Generales (Mes Actual)
     */
    public function kpis(): JsonResponse
    {
        $inicioMes = Carbon::now()->startOfMonth()->toDateString();
        $finMes    = Carbon::now()->endOfMonth()->toDateString();

        // Gastos del Mes (GASTO_PERSONAL)
        // Nota: RN-01 COMPRA_TERCERO no suma aquí
        $gastosMes = Operation::where('tipo_operacion', 'GASTO_PERSONAL')
            ->whereBetween('fecha_operacion', [$inicioMes, $finMes])
            ->sum('monto_pen');

        // Ingresos del Mes
        $ingresosMes = Operation::where('tipo_operacion', 'INGRESO_PERSONAL')
            ->whereBetween('fecha_operacion', [$inicioMes, $finMes])
            ->sum('monto_pen');

        // Cuentas por Cobrar Total (histórico activo)
        $cuentasCobrar = Operation::whereIn('tipo_operacion', ['COMPRA_TERCERO', 'PRESTAMO_OTORGADO'])
            ->whereIn('estado_deuda', ['PENDIENTE', 'PARCIAL'])
            ->sum('monto_saldo');
            
        // Cuentas por Pagar Total (histórico activo)
        $cuentasPagar = Operation::where('tipo_operacion', 'PRESTAMO_RECIBIDO')
            ->whereIn('estado_deuda', ['PENDIENTE', 'PARCIAL'])
            ->sum('monto_saldo');

        return response()->json([
            'data' => [
                'gastos_mes'            => (float) $gastosMes,
                'ingresos_mes'          => (float) $ingresosMes,
                'flujo_neto'            => (float) ($ingresosMes - $gastosMes),
                'cuentas_por_cobrar'    => (float) $cuentasCobrar,
                'cuentas_por_pagar'     => (float) $cuentasPagar,
                'patrimonio_deudas_neto'=> (float) ($cuentasCobrar - $cuentasPagar),
            ]
        ]);
    }

    /**
     * RF-05.2: Resumen de Tarjetas de Crédito
     */
    public function tarjetasCredito(): JsonResponse
    {
        $tarjetas = PaymentMethod::tarjetas()->activos()->get();
        $resumen = [];

        foreach ($tarjetas as $tarjeta) {
            try {
                $linea = $this->creditCardService->calcularLineaDisponible($tarjeta->id);
                $estadoCuenta = $tarjeta->es_tarjeta_completa 
                    ? $this->creditCardService->calcularEstadoDeCuenta($tarjeta->id)
                    : null;
                
                $resumen[] = [
                    'id'                  => $tarjeta->id,
                    'nombre'              => $tarjeta->nombre,
                    'linea_disponible'    => $linea['linea_disponible'],
                    'linea_total'         => $linea['linea_total'],
                    'consumos_pendientes' => $linea['consumos_pendientes'],
                    'estado_cuenta'       => $estadoCuenta ? [
                        'facturado_mes'   => $estadoCuenta['total_facturado'],
                        'vencimiento'     => $estadoCuenta['fecha_pago_limite'],
                        'ciclo_desde'     => $estadoCuenta['ciclo_desde'],
                        'ciclo_hasta'     => $estadoCuenta['ciclo_hasta'],
                        'cuotas_a_pagar'  => $estadoCuenta['cuotas_a_pagar'] ?? [],
                    ] : null,
                ];
            } catch (\Exception $e) {
                // Ignorar tarjetas mal configuradas para no romper todo el endpoint
            }
        }

        return response()->json(['data' => $resumen]);
    }

    /**
     * RF-05.5: Widget de Exigibilidad Mensual (Alertas de descalce)
     */
    public function exigibilidadMensual(): JsonResponse
    {
        $inicioMes = Carbon::now()->startOfMonth()->toDateString();
        $finMes    = Carbon::now()->endOfMonth()->toDateString();

        // A cobrar en el mes (operaciones y cuotas que vencen este mes)
        $cobrosOp = Operation::whereIn('tipo_operacion', ['COMPRA_TERCERO', 'PRESTAMO_OTORGADO'])
            ->whereIn('estado_deuda', ['PENDIENTE', 'PARCIAL'])
            ->where('es_diferida', false)
            ->whereBetween('fecha_vencimiento', [$inicioMes, $finMes])
            ->sum('monto_saldo');
            
        $cobrosCuotas = DB::table('installments as i')
            ->join('operations as o', 'o.id', '=', 'i.operation_id')
            ->whereIn('o.tipo_operacion', ['COMPRA_TERCERO', 'PRESTAMO_OTORGADO'])
            ->whereIn('i.estado', ['PENDIENTE', 'PARCIAL'])
            ->whereBetween('i.fecha_vencimiento', [$inicioMes, $finMes])
            ->whereNull('i.deleted_at')
            ->sum(DB::raw('i.monto_cuota - i.monto_abonado'));
            
        $totalACobrarMes = (float) $cobrosOp + (float) $cobrosCuotas;

        // A pagar en el mes (Préstamos recibidos)
        $pagosOp = Operation::where('tipo_operacion', 'PRESTAMO_RECIBIDO')
            ->whereIn('estado_deuda', ['PENDIENTE', 'PARCIAL'])
            ->where('es_diferida', false)
            ->whereBetween('fecha_vencimiento', [$inicioMes, $finMes])
            ->sum('monto_saldo');
            
        $pagosCuotas = DB::table('installments as i')
            ->join('operations as o', 'o.id', '=', 'i.operation_id')
            ->where('o.tipo_operacion', 'PRESTAMO_RECIBIDO')
            ->whereIn('i.estado', ['PENDIENTE', 'PARCIAL'])
            ->whereBetween('i.fecha_vencimiento', [$inicioMes, $finMes])
            ->whereNull('i.deleted_at')
            ->sum(DB::raw('i.monto_cuota - i.monto_abonado'));
            
        $totalAPagarMes = (float) $pagosOp + (float) $pagosCuotas;
        
        $descalce = $totalACobrarMes < $totalAPagarMes;

        return response()->json([
            'data' => [
                'a_cobrar_mes'   => $totalACobrarMes,
                'a_pagar_mes'    => $totalAPagarMes,
                'flujo_esperado' => $totalACobrarMes - $totalAPagarMes,
                'alerta_descalce'=> $descalce,
            ]
        ]);
    }
}
