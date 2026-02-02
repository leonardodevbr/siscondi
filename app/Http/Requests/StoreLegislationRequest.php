<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLegislationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('legislations.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'law_number' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*' => ['required', 'string', 'max:255'],
            'items' => ['array'],
            'items.*.functional_category' => ['required', 'string', 'max:255'],
            'items.*.daily_class' => ['required', 'string', 'max:100'],
            'items.*.values' => ['required', 'array'],
            'items.*.values.*' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'items.*.position_ids' => ['nullable', 'array'],
            'items.*.position_ids.*' => ['integer', 'exists:positions,id'],
        ];
    }
}
