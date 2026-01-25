<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('marketing.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => strtoupper(trim($this->code))]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id;

        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                'unique:coupons,code,' . $couponId,
                'regex:/^[A-Z0-9_-]+$/',
            ],
            'type' => ['sometimes', Rule::enum(CouponType::class)],
            'value' => ['sometimes', 'numeric', 'min:0.01'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}

