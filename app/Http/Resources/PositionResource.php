<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'municipality_id' => $this->municipality_id,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'role' => $this->role,
            'legislation_items' => $this->whenLoaded('legislationItems', fn () => $this->legislationItems->map(fn ($item) => [
                'id' => $item->id,
                'functional_category' => $item->functional_category,
                'daily_class' => $item->daily_class,
                'legislation_id' => $item->legislation_id,
            ])),
            'legislation_item_ids' => $this->whenLoaded('legislationItems', fn () => $this->legislationItems->pluck('id')->values()->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
