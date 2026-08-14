<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'fecha_pago'       => $this->fecha_pago?->toDateString(),
            'monto_original'   => (float) $this->monto_original,
            'moneda_original'  => $this->moneda_original?->value,
            'tipo_cambio'      => (float) $this->tipo_cambio,
            'monto_pen'        => (float) $this->monto_pen,
            'total_asignado'   => (float) $this->total_asignado, // Accessor
            'saldo_sin_asignar'=> (float) $this->saldo_sin_asignar, // Accessor
            'referencia'       => $this->referencia,
            'notas'            => $this->notas,
            'comprobante_url'  => $this->comprobante_url,
            'created_at'       => $this->created_at?->toIso8601String(),
            
            // Relaciones
            'payment_method'   => $this->whenLoaded('paymentMethod', function () {
                return [
                    'id'     => $this->paymentMethod->id,
                    'nombre' => $this->paymentMethod->nombre,
                    'tipo'   => $this->paymentMethod->tipo?->label(),
                ];
            }),
            'allocations'      => DebtAllocationResource::collection($this->whenLoaded('debtAllocations')),
        ];
    }
}
