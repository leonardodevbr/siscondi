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
            'items' => ['array'],
            'items.*.functional_category' => ['required', 'string', 'max:255'],
            'items.*.daily_class' => ['required', 'string', 'max:100'],
            'items.*.value_up_to_200km' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'items.*.value_above_200km' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'items.*.value_state_capital' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'items.*.value_other_capitals_df' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'items.*.value_exterior' => ['required', 'integer', 'min:0', 'max:9999999999'],
        ];
    }
}
