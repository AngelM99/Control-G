<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOperationRequest;
use App\Http\Resources\OperationResource;
use App\Models\Operation;
use App\Services\OperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OperationController extends Controller
{
    public function __construct(
        private readonly OperationService $operationService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Operation::with(['contact', 'category', 'paymentMethod']);

        // Filtros básicos
        if ($request->has('tipo_operacion')) {
            $query->where('tipo_operacion', $request->query('tipo_operacion'));
        }
        if ($request->has('estado_deuda')) {
            $query->where('estado_deuda', $request->query('estado_deuda'));
        }
        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->query('contact_id'));
        }

        $operations = $query->orderBy('fecha_operacion', 'desc')->paginate(20);

        return OperationResource::collection($operations);
    }

    public function store(StoreOperationRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        
        $operation = $this->operationService->register($dto);
        
        return response()->json([
            'message' => 'Operación registrada exitosamente.',
            'data'    => new OperationResource($operation),
        ], 201);
    }

    public function show(Operation $operation): JsonResponse
    {
        $operation->load(['contact', 'category', 'paymentMethod', 'installments', 'debtAllocations.payment']);
        
        return response()->json([
            'data' => new OperationResource($operation),
        ]);
    }

    public function destroy(Operation $operation, Request $request): JsonResponse
    {
        $motivo = $request->input('motivo', 'Anulación manual');
        
        $anulada = $this->operationService->anular($operation, $motivo);
        
        return response()->json([
            'message' => 'Operación anulada exitosamente.',
            'data'    => new OperationResource($anulada),
        ]);
    }
}
