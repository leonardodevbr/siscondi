<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Product;
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
            'customer_id' => ['nullable', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.installments' => ['nullable', 'integer', 'min:1'],
            'payments.*.card_authorization_code' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['nullable', 'string', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
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

            $productIds = array_column($items, 'product_id');
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $totalAmount = 0;
            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                if ($product) {
                    $totalAmount += $product->getEffectivePrice() * $item['quantity'];
                }
            }

            $discountType = $this->input('discount_type');
            $discountValue = (float) ($this->input('discount_value', 0));
            $discountAmount = (float) ($this->input('discount_amount', 0));

            if ($discountType === 'percentage') {
                $discountAmount = $totalAmount * ($discountValue / 100);
            } elseif ($discountType === 'fixed') {
                $discountAmount = $discountValue;
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
