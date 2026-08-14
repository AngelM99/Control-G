<?php

namespace App\Http\Requests;

use App\Models\Moneda;
use App\Models\TipoOperacion;
use App\Services\DTOs\CreateOperationDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_operacion'      => ['required', new Enum(TipoOperacion::class)],
            'contact_id'          => ['nullable', 'integer', 'exists:contacts,id'],
            'payment_method_id'   => ['nullable', 'integer', 'exists:payment_methods,id'],
            'category_id'         => ['nullable', 'integer', 'exists:categories,id'],
            'operation_origen_id' => ['nullable', 'integer', 'exists:operations,id'],
            'descripcion'         => ['required', 'string', 'max:255'],
            'fecha_operacion'     => ['required', 'date'],
            'fecha_vencimiento'   => ['nullable', 'date', 'after_or_equal:fecha_operacion'],
            'monto_original'      => ['required', 'numeric', 'min:0.01'],
            'moneda_original'     => ['required', new Enum(Moneda::class)],
            'tipo_cambio'         => ['nullable', 'numeric', 'min:0'],
            'es_diferida'         => ['boolean'],
            'numero_cuotas'       => ['integer', 'min:1'],
            'cuotas_personalizadas' => ['nullable', 'array'],
            'cuotas_personalizadas.*.monto' => ['required_with:cuotas_personalizadas', 'numeric', 'min:0.01'],
            'cuotas_personalizadas.*.fecha_vencimiento' => ['required_with:cuotas_personalizadas', 'date'],
            'cuotas_personalizadas.*.notas' => ['nullable', 'string'],
            'notas'               => ['nullable', 'string'],
            'comprobante_url'     => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $tipoOperacion = TipoOperacion::tryFrom($this->input('tipo_operacion'));

            if ($tipoOperacion) {
                if ($tipoOperacion->requiereContacto() && empty($this->input('contact_id'))) {
                    $validator->errors()->add('contact_id', "El tipo de operación '{$tipoOperacion->label()}' requiere un contacto.");
                }

                if ($tipoOperacion === TipoOperacion::Devolucion && empty($this->input('operation_origen_id'))) {
                    $validator->errors()->add('operation_origen_id', "Una devolución debe referenciar la operación original.");
                }
            }

            if ($this->input('moneda_original') === Moneda::USD->value && floatval($this->input('tipo_cambio')) <= 0) {
                $validator->errors()->add('tipo_cambio', 'El tipo de cambio es requerido y debe ser mayor a 0 para operaciones en USD.');
            }
            
            if ($this->boolean('es_diferida') && $this->input('numero_cuotas', 1) < 2) {
                $validator->errors()->add('numero_cuotas', 'Una operación diferida debe tener al menos 2 cuotas.');
            }
        });
    }

    public function toDTO(): CreateOperationDTO
    {
        return new CreateOperationDTO(
            tipoOperacion:   TipoOperacion::from($this->input('tipo_operacion')),
            contactId:       $this->input('contact_id'),
            paymentMethodId: $this->input('payment_method_id'),
            categoryId:      $this->input('category_id'),
            descripcion:     $this->input('descripcion'),
            fechaOperacion:  $this->input('fecha_operacion'),
            fechaVencimiento:$this->input('fecha_vencimiento'),
            montoOriginal:   (float) $this->input('monto_original'),
            monedaOriginal:  $this->input('moneda_original'),
            tipoCambio:      (float) $this->input('tipo_cambio', 1.0),
            esDiferida:      $this->boolean('es_diferida'),
            numeroCuotas:    (int) $this->input('numero_cuotas', 1),
            cuotasPersonalizadas: $this->input('cuotas_personalizadas', []),
            operationOrigenId: $this->input('operation_origen_id'),
            notas:           $this->input('notas'),
            comprobanteUrl:  $this->input('comprobante_url'),
        );
    }
}
