<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegislationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legislation_id' => $this->legislation_id,
            'functional_category' => $this->functional_category,
            'daily_class' => $this->daily_class,
            'values' => $this->values ?? [],
            'cargo_ids' => $this->whenLoaded('cargos', fn () => $this->cargos->pluck('id')->values()->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
