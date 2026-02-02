<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $servantsCount = (int) ($this->resource->getAttribute('servants_count') ?? 0);

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_main' => $this->is_main,
            'cnpj' => $this->fund_cnpj,
            'fund_cnpj' => $this->fund_cnpj,
            'fund_name' => $this->fund_name,
            'fund_code' => $this->fund_code,
            'logo_path' => $this->logo_path,
            'address' => $this->address,
            'neighborhood' => $this->neighborhood,
            'zip_code' => $this->zip_code,
            'phone' => $this->phone,
            'email' => $this->email,
            'servants_count' => $servantsCount,
            'parent' => new DepartmentResource($this->whenLoaded('parent')),
            'children' => DepartmentResource::collection($this->whenLoaded('children')),
            'can_delete' => ! $this->is_main && $servantsCount === 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
