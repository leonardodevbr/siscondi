<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreServantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('servants.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
            'legislation_item_id' => ['nullable', 'exists:legislation_items,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'size:11', 'unique:servants,cpf'],
            'rg' => ['required', 'string', 'max:20'],
            'organ_expeditor' => ['required', 'string', 'max:20'],
            'matricula' => ['required', 'string', 'max:50', 'unique:servants,matricula'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'agency_number' => ['nullable', 'string', 'max:10'],
            'account_number' => ['nullable', 'string', 'max:20'],
            'account_type' => ['nullable', 'in:corrente,poupanca'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'password' => ['required_with:email', 'nullable', 'string', 'confirmed', Password::defaults()],
            'cargo_id' => ['integer', 'exists:cargos,id'],
        ];
    }
}
