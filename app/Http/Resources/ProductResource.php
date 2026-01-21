<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'composition' => $this->composition,
            'cost_price' => $this->cost_price,
            'sell_price' => $this->sell_price,
            'promotional_price' => $this->promotional_price,
            'promotional_expires_at' => $this->promotional_expires_at,
            'effective_price' => $this->getEffectivePrice(),
            'has_active_promotion' => $this->hasActivePromotion(),
            'current_stock' => $this->when(isset($this->current_stock), fn () => $this->current_stock),
            'variants' => $this->whenLoaded('variants', fn () => ProductVariantResource::collection($this->variants)),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
