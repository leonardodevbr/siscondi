<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->relationLoaded('roles') && $this->roles->isNotEmpty()
            ? $this->roles->first()->name
            : null;

        $primaryDepartment = $this->getPrimaryDepartment();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'is_super_admin' => $this->hasRole('super-admin'),
            'municipality_id' => $this->municipality_id,
            'municipality' => $this->whenLoaded('municipality', fn () => $this->municipality ? [
                'id' => $this->municipality->id,
                'name' => $this->municipality->name,
            ] : null),
            'department_id' => $primaryDepartment?->id,
            'department' => $primaryDepartment ? [
                'id' => $primaryDepartment->id,
                'name' => $primaryDepartment->name,
            ] : null,
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'is_primary' => (int) $d->id === (int) $this->primary_department_id,
            ])),
            'department_ids' => $this->whenLoaded('departments', fn () => $this->departments->pluck('id')->toArray()),
            'primary_department_id' => $this->primary_department_id ?? $primaryDepartment?->id,
            'needs_primary_department' => $this->needsPrimaryDepartmentChoice(),
        ];
    }
}
