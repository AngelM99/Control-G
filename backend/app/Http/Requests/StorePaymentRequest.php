<?php

namespace App\Http\Requests;

use App\Models\Moneda;
use App\Services\DTOs\ApplyPaymentDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_original'     => ['required', 'numeric', 'min:0.01'],
            'moneda_original'    => ['required', new Enum(Moneda::class)],
            'tipo_cambio'        => ['nullable', 'numeric', 'min:0'],
            'payment_method_id'  => ['nullable', 'integer', 'exists:payment_methods,id'],
            'fecha_pago'         => ['nullable', 'date'],
            'referencia'         => ['nullable', 'string', 'max:150'],
            'notas'              => ['nullable', 'string'],
            'comprobante_url'    => ['nullable', 'string', 'max:500'],
            
            'modo_asignacion'    => ['required', 'string', 'in:auto,manual'],
            'contact_id'         => ['required_if:modo_asignacion,auto', 'nullable', 'integer', 'exists:contacts,id'],
            'tipo_operacion'     => ['nullable', 'string'],
            
            'asignaciones_manual' => ['required_if:modo_asignacion,manual', 'array'],
            'asignaciones_manual.*.operation_id' => ['required_with:asignaciones_manual', 'integer', 'exists:operations,id'],
            'asignaciones_manual.*.installment_id' => ['nullable', 'integer', 'exists:installments,id'],
            'asignaciones_manual.*.monto' => ['required_with:asignaciones_manual', 'numeric', 'min:0.01'],
        ];
    }
    
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('moneda_original') === Moneda::USD->value && floatval($this->input('tipo_cambio')) <= 0) {
                $validator->errors()->add('tipo_cambio', 'El tipo de cambio es requerido y debe ser mayor a 0 para operaciones en USD.');
            }
        });
    }

    public function toDTO(): ApplyPaymentDTO
    {
        return new ApplyPaymentDTO(
            montoOriginal:      (float) $this->input('monto_original'),
            monedaOriginal:     $this->input('moneda_original'),
            tipoCambio:         (float) $this->input('tipo_cambio', 1.0),
            paymentMethodId:    $this->input('payment_method_id'),
            fechaPago:          $this->input('fecha_pago', ''),
            referencia:         $this->input('referencia'),
            notas:              $this->input('notas'),
            comprobanteUrl:     $this->input('comprobante_url'),
            modoAsignacion:     $this->input('modo_asignacion'),
            asignacionesManual: $this->input('asignaciones_manual', []),
            contactId:          $this->input('contact_id'),
            tipoOperacion:      $this->input('tipo_operacion'),
        );
    }
}
