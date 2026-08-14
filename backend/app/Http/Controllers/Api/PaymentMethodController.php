<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of active payment methods.
     */
    public function index(): JsonResponse
    {
        $methods = PaymentMethod::where('estado', 'ACTIVO')
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get();
            
        return response()->json($methods);
    }

    /**
     * Update the specified payment method in storage.
     */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $validated = $request->validate([
            'dia_corte' => 'nullable|integer|min:1|max:31',
            'dia_pago' => 'nullable|integer|min:1|max:31',
            'linea_total' => 'nullable|numeric|min:0',
        ]);

        $paymentMethod->update($validated);

        return response()->json([
            'message' => 'Configuración de tarjeta actualizada.',
            'payment_method' => $paymentMethod
        ]);
    }
    /**
     * Store a newly created payment method in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'banco' => 'nullable|string|max:255',
            'tipo' => 'required|in:EFECTIVO,DEBITO,CREDITO,BILLETERA_DIGITAL,CUENTA_BANCARIA',
            'dia_corte' => 'nullable|integer|min:1|max:31',
            'dia_pago' => 'nullable|integer|min:1|max:31',
            'linea_total' => 'nullable|numeric|min:0',
        ]);

        $validated['estado'] = 'ACTIVO';
        
        $paymentMethod = PaymentMethod::create($validated);

        return response()->json([
            'message' => 'Medio de pago creado exitosamente.',
            'payment_method' => $paymentMethod
        ], 201);
    }
}
