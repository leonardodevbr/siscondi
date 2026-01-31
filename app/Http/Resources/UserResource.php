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
            'cargo_id' => $this->cargo_id,
            'cargo' => $this->whenLoaded('cargo', fn () => $this->cargo ? [
                'id' => $this->cargo->id,
                'name' => $this->cargo->name,
                'symbol' => $this->cargo->symbol,
                'role' => $this->cargo->role,
            ] : null),
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'is_super_admin' => $this->hasRole('super-admin'),
            'municipality_id' => $this->municipality_id,
            'municipality' => $this->whenLoaded('municipality', fn () => $this->municipality ? [
                'id' => $this->municipality->id,
                'name' => $this->municipality->name,
                'display_name' => $this->municipality->display_name,
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
            'signature_path' => $this->resource->signature_path ?? null,
            'signature_url' => $this->resource->signature_path
                ? rtrim(config('app.url'), '/').'/storage/'.ltrim($this->resource->signature_path, '/')
                : null,
            'servant' => $this->when($this->relationLoaded('servant'), fn () => $this->servant ? [
                'id' => $this->servant->id,
                'name' => $this->servant->name,
                'matricula' => $this->servant->matricula,
            ] : null),
            'servant_id' => $this->servant?->id ?? null,
            'has_operation_pin' => $this->resource->hasOperationPin(),
            'has_operation_password' => $this->resource->hasOperationPassword(),
            'requires_operation_credentials_to_sign' => $this->resource->requiresOperationCredentialsToSign(),
        ];
    }
}
