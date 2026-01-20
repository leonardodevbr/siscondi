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
            'image' => $this->image,
            'attributes' => $this->attributes ?? [],
            'description_full' => $this->description_full,
            'effective_price' => $this->getEffectivePrice(),
        ];
    }
}
