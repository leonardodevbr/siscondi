<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.edit') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = (int) $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'confirmed', Password::defaults()],
            'role' => ['sometimes', 'required', 'string', Rule::in(['seller', 'stockist', 'manager', 'super-admin'])],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'operation_pin' => ['sometimes', 'nullable', 'string', 'max:10', Rule::unique('users', 'operation_pin')->ignore($userId)],
            'operation_password' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && $value !== '' && strlen($value) < 4) {
                        $fail('A senha de operação deve ter no mínimo 4 caracteres.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'operation_pin' => 'PIN de autorização',
            'operation_password' => 'senha de operação',
        ];
    }
}
