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
            'items' => ['sometimes', 'array'],
            'items.*.functional_category' => ['required_with:items', 'string', 'max:255'],
            'items.*.daily_class' => ['required_with:items', 'string', 'max:100'],
            'items.*.value_up_to_200km' => ['required_with:items', 'integer', 'min:0', 'max:9999999999'],
            'items.*.value_above_200km' => ['required_with:items', 'integer', 'min:0', 'max:9999999999'],
            'items.*.value_state_capital' => ['required_with:items', 'integer', 'min:0', 'max:9999999999'],
            'items.*.value_other_capitals_df' => ['required_with:items', 'integer', 'min:0', 'max:9999999999'],
            'items.*.value_exterior' => ['required_with:items', 'integer', 'min:0', 'max:9999999999'],
        ];
    }
}
