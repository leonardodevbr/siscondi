<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
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
            'code' => $this->code,
            'type' => $this->type->value,
            'value' => $this->value,
            'max_discount_amount' => $this->max_discount_amount,
            'min_purchase_amount' => $this->min_purchase_amount,
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'usage_limit' => $this->usage_limit,
            'used_count' => $this->used_count,
            'remaining_uses' => $this->usage_limit !== null ? max(0, $this->usage_limit - $this->used_count) : null,
            'active' => $this->active,
            'is_valid' => $this->isValid(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
