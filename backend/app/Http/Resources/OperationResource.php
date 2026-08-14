<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'tipo_operacion'      => [
                'id'    => $this->tipo_operacion?->value,
                'label' => $this->tipo_operacion?->label(),
            ],
            'descripcion'         => $this->descripcion,
            'fecha_operacion'     => $this->fecha_operacion?->toDateString(),
            'fecha_vencimiento'   => $this->fecha_vencimiento?->toDateString(),
            
            // Montos
            'moneda_original'     => $this->moneda_original?->value,
            'monto_original'      => (float) $this->monto_original,
            'tipo_cambio'         => (float) $this->tipo_cambio,
            'monto_pen'           => (float) $this->monto_pen,
            'monto_abonado'       => (float) $this->monto_abonado,
            'monto_saldo'         => (float) $this->monto_saldo, // Generado
            
            // Estado y flags
            'estado_deuda'        => [
                'id'    => $this->estado_deuda?->value,
                'label' => $this->estado_deuda?->label(),
                'color' => $this->estado_deuda?->color(),
            ],
            'es_diferida'         => $this->es_diferida,
            'numero_cuotas'       => $this->numero_cuotas,
            
            // Metadatos
            'notas'               => $this->notas,
            'comprobante_url'     => $this->comprobante_url,
            'created_at'          => $this->created_at?->toIso8601String(),
            
            // Relaciones condicionales (si están cargadas con ->with())
            'contact'             => new ContactResource($this->whenLoaded('contact')),
            'category'            => $this->whenLoaded('category', function () {
                return [
                    'id'     => $this->category->id,
                    'nombre' => $this->category->nombre,
                    'icono'  => $this->category->icono,
                    'color'  => $this->category->color,
                ];
            }),
            'payment_method'      => $this->whenLoaded('paymentMethod', function () {
                return [
                    'id'     => $this->paymentMethod->id,
                    'nombre' => $this->paymentMethod->nombre,
                    'tipo'   => $this->paymentMethod->tipo?->label(),
                ];
            }),
            'installments'        => InstallmentResource::collection($this->whenLoaded('installments')),
            'operation_origen'    => new OperationResource($this->whenLoaded('operationOrigen')),
        ];
    }
}
