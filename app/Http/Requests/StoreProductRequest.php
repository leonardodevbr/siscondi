<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('products.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'promotional_price' => ['nullable', 'numeric', 'min:0'],
            'promotional_expires_at' => ['nullable', 'date'],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.barcode' => ['nullable', 'string', 'max:13'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.image' => ['nullable', 'image', 'max:2048'],
            'variants.*.attributes' => ['nullable', 'array'],
            'initial_stock' => ['nullable', 'array'],
            'initial_stock.*.quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
