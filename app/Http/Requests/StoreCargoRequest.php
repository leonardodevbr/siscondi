<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCargoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cargos.create') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:50', 'in:admin,requester,validator,authorizer,payer,super-admin'],
        ];
    }
}
