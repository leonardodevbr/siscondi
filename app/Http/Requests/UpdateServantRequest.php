<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('servants.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'legislation_item_id' => ['sometimes', 'nullable', 'exists:legislation_items,id'],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'cpf' => ['sometimes', 'string', 'size:11', Rule::unique('servants', 'cpf')->ignore($this->servant)],
            'rg' => ['sometimes', 'string', 'max:20'],
            'organ_expeditor' => ['sometimes', 'string', 'max:20'],
            'matricula' => ['sometimes', 'string', 'max:50', Rule::unique('servants', 'matricula')->ignore($this->servant)],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'agency_number' => ['sometimes', 'nullable', 'string', 'max:10'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'account_type' => ['sometimes', 'nullable', 'in:corrente,poupanca'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'cargo_ids' => ['sometimes', 'array', 'min:1'],
            'cargo_ids.*' => ['integer', 'exists:cargos,id'],
        ];
    }
}
