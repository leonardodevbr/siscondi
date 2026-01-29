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
            'code' => ['required', 'string', 'max:50', 'unique:legislations,code'],
            'title' => ['required', 'string', 'max:255'],
            'law_number' => ['required', 'string', 'max:100'],
            'daily_value' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
        ];
    }
}
