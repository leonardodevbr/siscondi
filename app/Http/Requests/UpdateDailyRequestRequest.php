<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('daily-requests.edit') && $this->dailyRequest->isEditable();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'servant_id' => ['sometimes', 'exists:servants,id'],
            'destination_city' => ['sometimes', 'string', 'max:255'],
            'destination_state' => ['sometimes', 'string', 'size:2'],
            'departure_date' => ['sometimes', 'date'],
            'return_date' => ['sometimes', 'date', 'after:departure_date'],
            'reason' => ['sometimes', 'string', 'max:1000'],
            'quantity_days' => ['sometimes', 'numeric', 'min:0.5', 'max:999.9'],
        ];
    }
}
