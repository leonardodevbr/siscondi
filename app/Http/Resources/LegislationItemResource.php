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
            'value_up_to_200km' => $this->value_up_to_200km,
            'value_above_200km' => $this->value_above_200km,
            'value_state_capital' => $this->value_state_capital,
            'value_other_capitals_df' => $this->value_other_capitals_df,
            'value_exterior' => $this->value_exterior,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
