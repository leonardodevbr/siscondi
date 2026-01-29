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

        $primaryDepartment = $this->relationLoaded('departments')
            ? $this->departments->where('pivot.is_primary', true)->first()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'is_super_admin' => $this->hasRole('super-admin'),
            'department_id' => $primaryDepartment?->id,
            'department' => $primaryDepartment ? [
                'id' => $primaryDepartment->id,
                'name' => $primaryDepartment->name,
            ] : null,
            'departments' => $this->whenLoaded('departments', fn () => $this->departments->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'is_primary' => $d->pivot->is_primary ?? false,
            ])),
            'department_ids' => $this->whenLoaded('departments', fn () => $this->departments->pluck('id')->toArray()),
            'primary_department_id' => $primaryDepartment?->id,
        ];
    }
}
