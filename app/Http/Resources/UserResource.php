<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->relationLoaded('roles') && $this->roles->isNotEmpty()
            ? $this->roles->first()->name
            : null;

        $primaryBranch = $this->relationLoaded('branches')
            ? $this->branches->where('pivot.is_primary', true)->first()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'is_super_admin' => $this->hasRole('super-admin'),
            'branch_id' => $primaryBranch?->id,
            'branch' => $primaryBranch ? [
                'id' => $primaryBranch->id,
                'name' => $primaryBranch->name,
            ] : null,
            'branches' => $this->whenLoaded('branches', fn () => $this->branches->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'is_primary' => $branch->pivot->is_primary ?? false,
            ])),
            'branch_ids' => $this->whenLoaded('branches', fn () => $this->branches->pluck('id')->toArray()),
            'primary_branch_id' => $primaryBranch?->id,
        ];
    }
}
