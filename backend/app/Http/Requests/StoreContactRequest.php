<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contactId = $this->route('contact') ? $this->route('contact')->id : null;
        
        \Log::info('StoreContactRequest payload:', $this->all());

        return [
            'dni'           => 'nullable|string|max:20|unique:contacts,dni,' . $contactId,
            'nombre'        => ['required', 'string', 'max:150'],
            'alias'         => ['nullable', 'string', 'max:80'],
            'telefono'      => ['nullable', 'string', 'max:20'],
            'correo'        => ['nullable', 'email', 'max:150'],
            'tipo_contacto' => ['nullable', \Illuminate\Validation\Rule::in(['DEUDOR', 'ACREEDOR', 'AMBOS'])],
            'estado'        => ['nullable', \Illuminate\Validation\Rule::in(['ACTIVO', 'INACTIVO'])],
            'notas'         => ['nullable', 'string'],
        ];
    }
}
