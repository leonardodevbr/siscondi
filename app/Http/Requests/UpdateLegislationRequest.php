<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('legislations', 'code')->ignore($this->legislation)],
            'title' => ['sometimes', 'string', 'max:255'],
            'law_number' => ['sometimes', 'string', 'max:100'],
            'daily_value' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
