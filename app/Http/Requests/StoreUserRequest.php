<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'matricula' => ['nullable', 'string', 'max:50', 'unique:users,matricula'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'in:admin,requester,validator,authorizer,payer,beneficiary,super-admin'],
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'primary_department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'operation_pin' => ['nullable', 'string', 'max:10', 'regex:/^[0-9]+$/', 'unique:users,operation_pin'],
            'operation_password' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && $value !== '' && strlen($value) < 4) {
                        $fail('A senha de operação deve ter no mínimo 4 caracteres.');
                    }
                },
            ],
            'signature' => ['nullable', 'file', 'image', 'max:2048'],
            'servant_id' => ['sometimes', 'nullable', 'integer', 'exists:servants,id'],
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
            'username' => 'nome de usuário',
            'matricula' => 'matrícula',
            'operation_pin' => 'PIN de autorização',
            'operation_password' => 'senha de operação',
        ];
    }
}
