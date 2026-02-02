<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'department_id' => $this->department_id,
            'name' => $this->name,
            'cpf' => $this->cpf,
            'formatted_cpf' => $this->formatted_cpf,
            'rg' => $this->rg,
            'organ_expeditor' => $this->organ_expeditor,
            'matricula' => $this->matricula,
            'bank_name' => $this->bank_name,
            'agency_number' => $this->agency_number,
            'account_number' => $this->account_number,
            'account_type' => $this->account_type,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'user' => new UserResource($this->whenLoaded('user')),
            'position_id' => $this->position_id,
            'position' => new PositionResource($this->whenLoaded('position')),
            'destination_options' => $this->when(
                $this->relationLoaded('position') || $this->relationLoaded('legislationItem'),
                fn () => $this->resource->getEffectiveLegislationItem()?->values ?? []
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
