<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'dni'            => $this->dni,
            'nombre'         => $this->nombre,
            'alias'          => $this->alias,
            'nombre_display' => $this->nombre_display, // Accessor
            'telefono'       => $this->telefono,
            'correo'         => $this->correo,
            'tipo_contacto'  => [
                'id'    => $this->tipo_contacto?->value,
                'label' => $this->tipo_contacto?->label(),
            ],
            'estado'         => [
                'id'    => $this->estado?->value,
                'label' => $this->estado?->label(),
            ],
            'notas'          => $this->notas,
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}
