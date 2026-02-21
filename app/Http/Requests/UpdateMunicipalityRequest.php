<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMunicipalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ignoreId = $this->route('id') ?? $this->user()?->municipality_id;
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('municipalities', 'slug')->ignore($ignoreId)],
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'state' => ['nullable', 'string', 'max:2'],
            'display_state' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
