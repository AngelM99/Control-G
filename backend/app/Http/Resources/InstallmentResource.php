<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'numero_cuota'      => $this->numero_cuota,
            'total_cuotas'      => $this->total_cuotas,
            'monto_cuota'       => (float) $this->monto_cuota,
            'monto_abonado'     => (float) $this->monto_abonado,
            'saldo'             => (float) $this->saldo, // Accessor
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),
            'fecha_pago'        => $this->fecha_pago?->toDateString(),
            'estado'            => [
                'id'    => $this->estado?->value,
                'label' => $this->estado?->label(),
            ],
            'esta_vencida'      => $this->esta_vencida, // Accessor
            'notas'             => $this->notas,

            // Historial de abonos parciales aplicados a esta cuota
            'pagos'             => $this->whenLoaded('debtAllocations', function () {
                return $this->debtAllocations
                    ->filter(fn ($alloc) => $alloc->payment)
                    ->map(fn ($alloc) => [
                        'id'          => $alloc->payment->id,
                        'fecha'       => $alloc->payment->fecha_pago?->toDateString(),
                        'monto'       => (float) $alloc->monto_asignado,
                        'referencia'  => $alloc->payment->referencia,
                        'metodo'      => optional($alloc->payment->paymentMethod)->nombre,
                    ])
                    ->values();
            }),
        ];
    }
}
