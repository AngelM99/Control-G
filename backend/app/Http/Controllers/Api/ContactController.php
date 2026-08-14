<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\OperationResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $contacts = Contact::activos()->orderBy('nombre')->get();
        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = Contact::create($request->validated());
        
        return response()->json([
            'message' => 'Contacto creado exitosamente.',
            'data'    => new ContactResource($contact),
        ], 201);
    }

    public function update(StoreContactRequest $request, Contact $contact): JsonResponse
    {
        $contact->update($request->validated());
        
        return response()->json([
            'message' => 'Contacto actualizado exitosamente.',
            'data'    => new ContactResource($contact),
        ]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        // RN-04: Soft delete (cambio de estado en mantenedores)
        $contact->update(['estado' => 'INACTIVO']);
        
        return response()->json([
            'message' => 'Contacto desactivado exitosamente.',
        ]);
    }

    /**
     * RF-01.3: Ficha Consolidada por Contacto
     */
    public function fichaConsolidada(Contact $contact): JsonResponse
    {
        // Operaciones activas (PENDIENTE o PARCIAL)
        $operacionesActivas = $contact->deudasActivas()
            ->with([
                'category',
                'paymentMethod',
                'installments.debtAllocations.payment',
            ])
            ->orderBy('fecha_operacion', 'asc')
            ->get();
            
        // Cálculos de saldo pendiente
        $saldoCobrar = $operacionesActivas->filter(fn($op) => in_array($op->tipo_operacion?->value, ['COMPRA_TERCERO', 'PRESTAMO_OTORGADO']))->sum('monto_saldo');
        $saldoPagar  = $operacionesActivas->filter(fn($op) => $op->tipo_operacion?->value === 'PRESTAMO_RECIBIDO')->sum('monto_saldo');

        // Histórico general
        $historico = $contact->operations()->get();
        $historicoCobrado = $historico->filter(fn($op) => in_array($op->tipo_operacion?->value, ['COMPRA_TERCERO', 'PRESTAMO_OTORGADO']))->sum('monto_abonado');
        $historicoPagado  = $historico->filter(fn($op) => $op->tipo_operacion?->value === 'PRESTAMO_RECIBIDO')->sum('monto_abonado');

        // Timeline de abonos relacionados a este contacto
        // Buscamos allocations de operaciones de este contacto
        $ultimosAbonos = \App\Models\DebtAllocation::with('payment')
            ->whereHas('operation', fn($q) => $q->where('contact_id', $contact->id))
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn($alloc) => [
                'fecha'          => $alloc->payment->fecha_pago->toDateString(),
                'monto'          => (float) $alloc->monto_asignado,
                'operacion_desc' => $alloc->operation->descripcion,
                'referencia'     => $alloc->payment->referencia,
            ]);

        return response()->json([
            'contacto' => new ContactResource($contact),
            'resumen'  => [
                'saldo_a_cobrar'    => (float) $saldoCobrar,
                'saldo_a_pagar'     => (float) $saldoPagar,
                'historico_cobrado' => (float) $historicoCobrado,
                'historico_pagado'  => (float) $historicoPagado,
                'saldo_neto'        => (float) ($saldoCobrar - $saldoPagar),
            ],
            'deudas_activas' => OperationResource::collection($operacionesActivas),
            'ultimos_abonos' => $ultimosAbonos,
        ]);
    }

    /**
     * Búsqueda Rápida de Deuda por DNI y Periodo
     */
    public function searchByDni(\Illuminate\Http\Request $request): JsonResponse
    {
        $dni = $request->query('dni');
        $periodo = $request->query('periodo'); // Formato esperado: YYYY-MM
        
        if (!$dni) {
            return response()->json(['message' => 'El DNI es requerido.'], 400);
        }

        $contact = Contact::where('dni', $dni)->first();
        if (!$contact) {
            return response()->json(['message' => 'No se encontró un contacto con ese DNI.'], 404);
        }

        // Obtener operaciones activas
        $query = $contact->deudasActivas()->with(['category', 'paymentMethod', 'installments']);

        // Si mandan periodo, filtramos por la fecha de operación (para simplificar por ahora, asumiendo que el periodo de la deuda corresponde a la fecha_operacion o fecha_vencimiento si existiera)
        if ($periodo) {
            $query->whereRaw("DATE_FORMAT(fecha_operacion, '%Y-%m') = ?", [$periodo]);
        }

        $operaciones = $query->orderBy('fecha_operacion', 'asc')->get();
        $totalDeuda = $operaciones->filter(fn($op) => in_array($op->tipo_operacion?->value, ['COMPRA_TERCERO', 'PRESTAMO_OTORGADO']))->sum('monto_saldo');
        
        return response()->json([
            'contacto' => new ContactResource($contact),
            'total_deuda' => (float) $totalDeuda,
            'operaciones' => OperationResource::collection($operaciones),
        ]);
    }

    /**
     * Reporte mensual de deudores agrupado por contacto.
     *
     * Query params:
     *  - periodo (YYYY-MM) obligatorio
     *  - criterio: 'fecha_operacion' (por compra) | 'fecha_vencimiento' (por vencimiento de cuotas)
     */
    public function deudasPorPeriodo(\Illuminate\Http\Request $request): JsonResponse
    {
        $periodo  = $request->query('periodo');
        $criterio = $request->query('criterio', 'fecha_operacion');

        if (!$periodo || !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            return response()->json(['message' => 'El parámetro periodo (YYYY-MM) es requerido.'], 400);
        }

        if (!in_array($criterio, ['fecha_operacion', 'fecha_vencimiento'], true)) {
            return response()->json(['message' => 'Criterio inválido. Use fecha_operacion o fecha_vencimiento.'], 400);
        }

        $query = \App\Models\Operation::query()
            ->deudasActivas()
            ->with(['category', 'paymentMethod', 'contact', 'installments.debtAllocations.payment'])
            ->whereNotNull('contact_id')
            ->whereIn('tipo_operacion', ['COMPRA_TERCERO', 'PRESTAMO_OTORGADO']);

        if ($criterio === 'fecha_operacion') {
            // Compras registradas dentro del mes del periodo
            $query->whereRaw("DATE_FORMAT(fecha_operacion, '%Y-%m') = ?", [$periodo]);
        } else {
            // Deudas cuyo vencimiento (operación o cuota) cae dentro del periodo
            $query->where(function ($q) use ($periodo) {
                $q->whereRaw("DATE_FORMAT(fecha_vencimiento, '%Y-%m') = ?", [$periodo])
                  ->orWhereHas('installments', function ($qi) use ($periodo) {
                      $qi->whereRaw("DATE_FORMAT(fecha_vencimiento, '%Y-%m') = ?", [$periodo]);
                  });
            });
        }

        $operaciones = $query
            ->orderBy('fecha_operacion', 'asc')
            ->get();

        $agrupadas = $operaciones->groupBy('contact_id');

        $contactos = $agrupadas->map(function ($ops) {
            $contact = $ops->first()->contact;
            return [
                'contacto'     => $contact ? new ContactResource($contact) : null,
                'contact_id'   => $ops->first()->contact_id,
                'total_deuda'  => (float) $ops->sum('monto_saldo'),
                'operaciones'  => OperationResource::collection($ops),
            ];
        })->values();

        return response()->json([
            'periodo'       => $periodo,
            'criterio'      => $criterio,
            'total_general' => (float) $operaciones->sum('monto_saldo'),
            'cantidad_deudores' => $contactos->count(),
            'contactos'     => $contactos,
        ]);
    }
}
