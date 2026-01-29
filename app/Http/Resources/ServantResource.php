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
            'legislation_item_id' => $this->legislation_item_id,
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
            'legislation_item' => new LegislationItemResource($this->whenLoaded('legislationItem')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'user' => new UserResource($this->whenLoaded('user')),
            'cargo_ids' => $this->whenLoaded('cargos', fn () => $this->cargos->pluck('id')->values()->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
