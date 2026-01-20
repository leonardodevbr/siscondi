<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Coupon;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('pos.access') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.installments' => ['nullable', 'integer', 'min:1'],
            'payments.*.card_authorization_code' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $items = $this->input('items', []);
            $payments = $this->input('payments', []);

            $variantIds = array_column($items, 'product_variant_id');
            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->with('product')
                ->get()
                ->keyBy('id');

            $totalAmount = 0;
            foreach ($items as $item) {
                $variant = $variants->get($item['product_variant_id']);
                if ($variant) {
                    $totalAmount += $variant->getEffectivePrice() * $item['quantity'];
                }
            }

            $discountType = $this->input('discount_type');
            $discountValue = (float) ($this->input('discount_value', 0));
            $discountAmount = (float) ($this->input('discount_amount', 0));
            $couponCode = $this->input('coupon_code');

            if ($discountType === 'percentage') {
                $discountAmount = $totalAmount * ($discountValue / 100);
            } elseif ($discountType === 'fixed') {
                $discountAmount = $discountValue;
            }

            if ($couponCode) {
                $coupon = Coupon::where('code', strtoupper((string) $couponCode))->first();

                if ($coupon) {
                    $discountAmount = $coupon->calculateDiscount($totalAmount);
                }
            }

            $finalAmount = $totalAmount - $discountAmount;
            $paymentsTotal = array_sum(array_column($payments, 'amount'));

            if (abs($paymentsTotal - $finalAmount) > 0.01) {
                $validator->errors()->add(
                    'payments',
                    "The sum of payments ({$paymentsTotal}) must equal the final amount ({$finalAmount})."
                );
            }
        });
    }
}
