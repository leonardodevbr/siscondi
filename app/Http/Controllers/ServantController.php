<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreServantRequest;
use App\Http\Requests\UpdateServantRequest;
use App\Http\Resources\ServantResource;
use App\Models\Department;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ServantController extends Controller
{
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $this->authorize('servants.view');

        $with = ['department', 'user', 'position'];
        if ($request->boolean('for_daily_form')) {
            $with[] = 'position.legislationItems';
        }
        $query = Servant::with($with);

        if ($request->has('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%")
                  ->orWhere('matricula', 'like', "%{$search}%");
            });
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }


        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy('name', 'asc');

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        if ($request->boolean('all')) {
            return response()->json(ServantResource::collection($query->get()));
        }

        return ServantResource::collection($query->paginate($perPage));
    }

    public function store(StoreServantRequest $request): JsonResponse
    {
        $this->authorize('servants.create');
        
        $user = auth()->user();
        $data = $request->validated();
        $password = $data['password'] ?? null;
        unset($data['password']);
        
        // Verifica se pode criar servidor neste departamento
        if (!$user->hasRole('super-admin')) {
            if ($user->hasRole('admin')) {
                $department = Department::find($data['department_id']);
                if ($department && $department->municipality_id !== $user->municipality_id) {
                    abort(403, 'Você só pode criar servidores em secretarias do seu município.');
                }
            } else {
                if (!$user->hasAccessToDepartment($data['department_id'])) {
                    abort(403, 'Você não tem permissão para criar servidores nesta secretaria.');
                }
            }
        }

        $servant = DB::transaction(function () use ($data, $password): Servant {
            $userId = null;
            if (! empty($data['email']) && $password !== null) {
                $department = Department::find($data['department_id']);
                $municipalityId = $department?->municipality_id;

                $newUser = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $password,
                    'municipality_id' => $municipalityId,
                ]);
                $newUser->syncRoles(['beneficiary']);
                if ($data['department_id']) {
                    $newUser->departments()->attach($data['department_id'], ['is_primary' => true]);
                    $newUser->update(['primary_department_id' => $data['department_id']]);
                }
                $userId = $newUser->id;
            }

            $data['user_id'] = $userId;
            $servant = Servant::create($data);

            return $servant;
        });

        $servant->load(['department', 'user', 'position']);

        return response()->json(new ServantResource($servant), 201);
    }

    public function show(string|int $servant): JsonResponse
    {
        $servant = Servant::query()
            ->with(['department', 'user', 'user.roles', 'user.departments', 'position'])
            ->findOrFail((int) $servant);
        $this->authorize('servants.view');

        return response()->json(new ServantResource($servant));
    }

    public function update(UpdateServantRequest $request, string|int $servant): JsonResponse
    {
        $this->authorize('servants.edit');
        
        $user = auth()->user();
        $servant = Servant::query()->findOrFail((int) $servant);
        $data = $request->validated();

        // Verifica se pode editar este servidor
        if (!$user->hasRole('super-admin')) {
            if ($user->hasRole('admin')) {
                if ($servant->department && $servant->department->municipality_id !== $user->municipality_id) {
                    abort(403, 'Você só pode editar servidores do seu município.');
                }
                // Se está mudando de departamento, verifica o novo também
                if (isset($data['department_id']) && $data['department_id'] !== $servant->department_id) {
                    $newDepartment = Department::find($data['department_id']);
                    if ($newDepartment && $newDepartment->municipality_id !== $user->municipality_id) {
                        abort(403, 'Você só pode transferir servidores para secretarias do seu município.');
                    }
                }
            } else {
                if (!$user->hasAccessToDepartment($servant->department_id)) {
                    abort(403, 'Você não tem permissão para editar este servidor.');
                }
                if (isset($data['department_id']) && $data['department_id'] !== $servant->department_id) {
                    if (!$user->hasAccessToDepartment($data['department_id'])) {
                        abort(403, 'Você não tem permissão para transferir servidores para esta secretaria.');
                    }
                }
            }
        }

        if (array_key_exists('email', $data) && $servant->user_id) {
            $user = $servant->user;
            if ($user && $user->email !== $data['email']) {
                $user->update(['email' => $data['email']]);
            }
        }

        $servant->update($data);
        $servant->load(['department', 'user', 'position']);

        return response()->json(new ServantResource($servant));
    }

    public function destroy(string|int $servant): JsonResponse
    {
        $servant = Servant::query()->findOrFail((int) $servant);
        $this->authorize('servants.delete');

        // Verifica se há solicitações de diárias vinculadas
        if ($servant->dailyRequests()->exists()) {
            return response()->json([
                'message' => 'Não é possível deletar um servidor com solicitações de diárias vinculadas.',
            ], 422);
        }

        $servant->delete();

        return response()->json(['message' => 'Servidor deletado com sucesso.']);
    }
}
