<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
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
            'product_variant' => $this->whenLoaded('productVariant', function () {
                return [
                    'id' => $this->productVariant->id,
                    'sku' => $this->productVariant->sku,
                    'description_full' => $this->productVariant->description_full,
                    'product' => [
                        'id' => $this->productVariant->product->id,
                        'name' => $this->productVariant->product->name,
                    ],
                ];
            }),
            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'type' => $this->type->value,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
