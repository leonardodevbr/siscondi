<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegislationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('legislations.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'law_number' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'destinations' => ['sometimes', 'array', 'min:1'],
            'destinations.*' => ['required_with:destinations', 'string', 'max:255'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['nullable', 'integer', 'exists:legislation_items,id'],
            'items.*.functional_category' => ['required_with:items', 'string', 'max:255'],
            'items.*.daily_class' => ['required_with:items', 'string', 'max:100'],
            'items.*.values' => ['required_with:items', 'array'],
            'items.*.values.*' => ['required_with:items', 'integer', 'min:0', 'max:9999999999'],
            'items.*.cargo_ids' => ['nullable', 'array'],
            'items.*.cargo_ids.*' => ['integer', 'exists:cargos,id'],
        ];
    }
}
