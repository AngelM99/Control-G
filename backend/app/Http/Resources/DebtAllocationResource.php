<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'monto_asignado' => (float) $this->monto_asignado,
            'es_cuota'       => $this->es_cuota,
            
            // Relaciones simplificadas para evitar recursión profunda
            'operation'      => $this->whenLoaded('operation', function () {
                return [
                    'id'             => $this->operation->id,
                    'descripcion'    => $this->operation->descripcion,
                    'tipo_operacion' => $this->operation->tipo_operacion?->label(),
                ];
            }),
            'installment'    => $this->whenLoaded('installment', function () {
                return [
                    'id'           => $this->installment->id,
                    'numero_cuota' => $this->installment->numero_cuota,
                ];
            }),
        ];
    }
}
