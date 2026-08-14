<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\DebtPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly DebtPaymentService $paymentService
    ) {}

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        
        $resultado = $this->paymentService->apply($dto);
        
        return response()->json([
            'message' => 'Abono registrado exitosamente.',
            'data'    => $resultado->toArray(),
        ], 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['paymentMethod', 'debtAllocations.operation', 'debtAllocations.installment']);
        
        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function destroy(Payment $payment, Request $request): JsonResponse
    {
        $motivo = $request->input('motivo', 'Cancelación manual');
        
        $this->paymentService->cancelar($payment, $motivo);
        
        return response()->json([
            'message' => 'Abono cancelado exitosamente. Los saldos han sido restaurados.',
        ]);
    }
}
