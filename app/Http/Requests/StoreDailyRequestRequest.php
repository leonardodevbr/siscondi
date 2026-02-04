<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('daily-requests.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'servant_id' => ['required', 'exists:servants,id'],
            'destination_type' => ['required', 'string', 'max:255'],
            'destination_city' => ['required', 'string', 'max:255'],
            'destination_state' => ['required', 'string', 'size:2'],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'return_date' => ['required', 'date', 'after_or_equal:departure_date'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'reason' => ['required', 'string', 'max:1000'],
            'quantity_days' => ['required', 'numeric', 'min:0.5', 'max:999.9'],
        ];
    }

    public function messages(): array
    {
        return [
            'departure_date.after_or_equal' => 'A data de saída deve ser hoje ou uma data futura.',
            'return_date.after_or_equal' => 'A data de retorno deve ser igual ou posterior à data de saída.',
        ];
    }
}
