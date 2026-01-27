<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'user_name' => $this->user->name,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null,
            'customer_name' => $this->customer?->name ?? 'Cliente Avulso',
            'coupon' => $this->whenLoaded('coupon', fn () => $this->coupon ? [
                'id' => $this->coupon->id,
                'code' => $this->coupon->code,
                'type' => $this->coupon->type->value,
                'value' => $this->coupon->value,
            ] : null),
            'total_amount' => $this->total_amount,
            'discount_amount' => $this->discount_amount,
            'final_amount' => $this->final_amount,
            'status' => $this->status->value,
            'note' => $this->note,
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                    ],
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                ];
            }),
            'payments' => $this->payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'method' => $payment->method->value,
                    'amount' => $payment->amount,
                    'installments' => $payment->installments,
                    'card_authorization_code' => $payment->card_authorization_code,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
