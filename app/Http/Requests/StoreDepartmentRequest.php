<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('departments.create') ?? false;
    }


    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_main' => ['nullable', 'boolean'],
            'fund_cnpj' => ['nullable', 'string', 'max:18'],
            'fund_name' => ['nullable', 'string', 'max:255'],
            'fund_code' => ['nullable', 'string', 'max:50'],
            'logo_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
