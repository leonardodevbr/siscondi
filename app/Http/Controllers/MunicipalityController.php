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
        
        $user = auth()->user();
        
        // Apenas super-admin pode listar todos os municípios
        if (!$user || !$user->hasRole('super-admin')) {
            abort(403, 'Apenas super-administradores podem listar todos os municípios.');
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
        if (! $municipality || ! $municipality->getAttribute('id')) {
            return response()->json(['message' => 'Usuário sem município vinculado (secretaria principal).'], 404);
        }

        $municipality = Municipality::query()->with('departments')->find($municipality->getAttribute('id'));
        if (! $municipality) {
            return response()->json(['message' => 'Município não encontrado.'], 404);
        }

        return response()->json(new MunicipalityResource($municipality));
    }

    /**
     * Exibe um município por ID (apenas SuperAdmin).
     */
    public function show(string|int $id): JsonResponse
    {
        $user = auth()->user();
        
        // Super-admin pode ver qualquer município
        if ($user && $user->hasRole('super-admin')) {
            $municipality = Municipality::query()->with('departments')->findOrFail((int) $id);
            return response()->json(new MunicipalityResource($municipality));
        }
        
        // Admin só pode ver o seu próprio município
        if ($user && $user->hasRole('admin') && $user->municipality_id === (int) $id) {
            $municipality = Municipality::query()->with('departments')->findOrFail((int) $id);
            return response()->json(new MunicipalityResource($municipality));
        }
        
        abort(403, 'Você não tem permissão para visualizar este município.');
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

        $fresh = Municipality::query()->with('departments')->find($municipality->getAttribute('id'));

        return response()->json(new MunicipalityResource($fresh));
    }

    /**
     * Atualiza um município por ID (apenas SuperAdmin).
     */
    public function update(UpdateMunicipalityRequest $request, string|int $id): JsonResponse
    {
        $user = auth()->user();
        
        // Super-admin pode editar qualquer município
        if ($user && $user->hasRole('super-admin')) {
            $municipality = Municipality::query()->findOrFail((int) $id);
            $municipality->update($request->validated());
            
            $fresh = Municipality::query()->with('departments')->findOrFail($municipality->id);
            return response()->json(new MunicipalityResource($fresh));
        }
        
        // Admin só pode editar o seu próprio município
        if ($user && $user->hasRole('admin') && $user->municipality_id === (int) $id) {
            $municipality = Municipality::query()->findOrFail((int) $id);
            $municipality->update($request->validated());
            
            $fresh = Municipality::query()->with('departments')->findOrFail($municipality->id);
            return response()->json(new MunicipalityResource($fresh));
        }
        
        abort(403, 'Você não tem permissão para editar este município.');
    }

    /**
     * Lista secretarias do município (SuperAdmin por ID; admin usa current com departments).
     */
    public function departments(string|int $id): JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        $municipality = Municipality::query()->findOrFail((int) $id);

        // Super-admin pode listar secretarias de qualquer município
        if (!$user->hasRole('super-admin')) {
            // Admin só pode listar secretarias do seu município
            if (!$user->hasRole('admin') || $user->municipality_id !== (int) $id) {
                abort(403, 'Você não tem permissão para listar as secretarias deste município.');
            }
        }

        $departments = $municipality->departments()
            ->withCount('servants')
            ->orderBy('is_main', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(DepartmentResource::collection($departments));
    }
}
