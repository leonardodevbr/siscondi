<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MunicipalityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $m = $this->resource;
        if ($m === null) {
            return [
                'id' => null,
                'name' => null,
                'display_name' => null,
                'cnpj' => null,
                'state' => null,
                'display_state' => null,
                'address' => null,
                'email' => null,
                'logo_path' => null,
                'departments' => [],
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        return [
            'id' => $m->getAttribute('id'),
            'name' => $m->getAttribute('name'),
            'display_name' => $m->getAttribute('display_name'),
            'cnpj' => $m->getAttribute('cnpj'),
            'state' => $m->getAttribute('state'),
            'display_state' => $m->getAttribute('display_state'),
            'address' => $m->getAttribute('address'),
            'email' => $m->getAttribute('email'),
            'logo_path' => $m->getAttribute('logo_path'),
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
            'created_at' => $m->getAttribute('created_at'),
            'updated_at' => $m->getAttribute('updated_at'),
        ];
    }
}
