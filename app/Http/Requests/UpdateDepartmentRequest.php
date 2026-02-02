<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('departments.edit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cnpj') && ! $this->has('fund_cnpj')) {
            $this->merge(['fund_cnpj' => $this->input('cnpj')]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_main' => ['nullable', 'boolean'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'fund_cnpj' => ['nullable', 'string', 'max:18'],
            'fund_name' => ['nullable', 'string', 'max:255'],
            'fund_code' => ['nullable', 'string', 'max:50'],
            'logo_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
