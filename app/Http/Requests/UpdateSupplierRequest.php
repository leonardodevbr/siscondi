<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('products.edit') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supplierId = $this->route('supplier');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['sometimes', 'required', 'string', 'max:18', Rule::unique('suppliers', 'cnpj')->ignore($supplierId)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
