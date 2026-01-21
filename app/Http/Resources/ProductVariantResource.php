<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
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
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price' => $this->price,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'attributes' => $this->attributes ?? [],
            'description_full' => $this->description_full,
            'effective_price' => $this->getEffectivePrice(),
            'current_stock' => $this->when(isset($this->current_stock), fn () => $this->current_stock),
            'inventories' => $this->whenLoaded('inventories', fn () => $this->inventories->map(function ($inventory) {
                return [
                    'id' => $inventory->id,
                    'branch_id' => $inventory->branch_id,
                    'branch_name' => $inventory->branch->name ?? null,
                    'quantity' => $inventory->quantity,
                    'min_quantity' => $inventory->min_quantity,
                ];
            })),
        ];
    }
}
