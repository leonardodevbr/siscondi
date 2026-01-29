<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMunicipalityRequest;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\MunicipalityResource;
use App\Models\Department;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;

class MunicipalityController extends Controller
{
    /**
     * Lista todos os municípios (apenas SuperAdmin).
     */
    public function index(): JsonResponse
    {
        $this->authorize('settings.manage');
        if (! auth()->user()?->hasRole('super-admin')) {
            abort(403, 'Apenas Super Admin pode listar municípios.');
        }

        $municipalities = Municipality::query()->orderBy('name')->get();

        return response()->json(MunicipalityResource::collection($municipalities));
    }

    /**
     * Retorna o município do usuário logado (admin vê o seu; SuperAdmin pode enviar header ou ver o primeiro).
     */
    public function current(): JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }
        $municipality = $user->getMunicipality();
        if (! $municipality) {
            return response()->json(['message' => 'Usuário sem município vinculado (secretaria principal).'], 404);
        }

        return response()->json(new MunicipalityResource($municipality->load('departments')));
    }

    /**
     * Exibe um município por ID (apenas SuperAdmin).
     */
    public function show(Municipality $municipality): JsonResponse
    {
        if (! auth()->user()?->hasRole('super-admin')) {
            abort(403, 'Apenas Super Admin pode visualizar município por ID.');
        }

        return response()->json(new MunicipalityResource($municipality->load('departments')));
    }

    /**
     * Atualiza o município do usuário logado (admin atualiza o seu).
     */
    public function updateCurrent(UpdateMunicipalityRequest $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }
        $municipality = $user->getMunicipality();
        if (! $municipality) {
            return response()->json(['message' => 'Usuário sem município vinculado.'], 404);
        }
        $municipality->update($request->validated());

        return response()->json(new MunicipalityResource($municipality->fresh()));
    }

    /**
     * Atualiza um município por ID (apenas SuperAdmin).
     */
    public function update(UpdateMunicipalityRequest $request, Municipality $municipality): JsonResponse
    {
        if (! auth()->user()?->hasRole('super-admin')) {
            abort(403, 'Apenas Super Admin pode editar município por ID.');
        }
        $municipality->update($request->validated());

        return response()->json(new MunicipalityResource($municipality->fresh()));
    }

    /**
     * Lista secretarias do município (SuperAdmin por ID; admin usa current com departments).
     */
    public function departments(Municipality $municipality): JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }
        if (! $user->hasRole('super-admin')) {
            $mine = $user->getMunicipality();
            if (! $mine || $mine->id !== $municipality->id) {
                abort(403, 'Acesso negado a este município.');
            }
        }
        $departments = $municipality->departments()->orderBy('is_main', 'desc')->orderBy('name')->get();

        return response()->json(DepartmentResource::collection($departments));
    }
}
