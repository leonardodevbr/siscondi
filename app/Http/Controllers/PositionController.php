<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PositionController extends Controller
{
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $this->authorize('positions.view');

        $user = auth()->user();
        $query = Position::query()->with('legislationItems');

        // Super-admin vê todos os cargos
        if (!$user->hasRole('super-admin')) {
            // Admin vê apenas cargos do seu município
            if ($user->hasRole('admin') && $user->municipality_id) {
                $query->where('municipality_id', $user->municipality_id);
            } else {
                // Outros usuários veem cargos do seu município
                if ($user->municipality_id) {
                    $query->where('municipality_id', $user->municipality_id);
                }
            }
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('symbol', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $query->orderBy('symbol', 'asc');

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        if ($request->boolean('all')) {
            return response()->json(PositionResource::collection($query->get()));
        }

        return PositionResource::collection($query->paginate($perPage));
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $this->authorize('positions.create');

        $user = auth()->user();
        $data = $request->validated();

        // Super-admin pode criar cargo em qualquer município
        if (!$user->hasRole('super-admin')) {
            // Admin só pode criar cargos no seu município
            if ($user->hasRole('admin')) {
                if (!isset($data['municipality_id']) || $data['municipality_id'] !== $user->municipality_id) {
                    abort(403, 'Você só pode criar cargos no seu município.');
                }
            } else {
                // Outros perfis precisam ter município definido
                if (!isset($data['municipality_id'])) {
                    $data['municipality_id'] = $user->municipality_id;
                }
                if ($data['municipality_id'] !== $user->municipality_id) {
                    abort(403, 'Você só pode criar cargos no seu município.');
                }
            }
        }

        $position = Position::create($data);

        return response()->json(new PositionResource($position), 201);
    }

    private function ensurePositionScope(Position $position): void
    {
        $user = auth()->user();

        // Super-admin tem acesso total
        if ($user && $user->hasRole('super-admin')) {
            return;
        }

        // Admin tem acesso aos cargos do seu município
        if ($user && $user->hasRole('admin') && $user->municipality_id === $position->municipality_id) {
            return;
        }

        // Outros usuários têm acesso aos cargos do seu município
        if ($user && $user->municipality_id === $position->municipality_id) {
            return;
        }

        abort(403, 'Você não tem permissão para acessar este cargo.');
    }

    public function show(string|int $position): JsonResponse
    {
        $position = Position::query()->with('legislationItems')->findOrFail((int) $position);
        $this->authorize('positions.view');
        $this->ensurePositionScope($position);

        return response()->json(new PositionResource($position));
    }

    public function update(UpdatePositionRequest $request, string|int $position): JsonResponse
    {
        $this->authorize('positions.edit');

        $user = auth()->user();
        $position = Position::query()->findOrFail((int) $position);
        $this->ensurePositionScope($position);
        $data = $request->validated();

        // Super-admin pode atualizar qualquer cargo
        if (!$user->hasRole('super-admin')) {
            // Admin só pode atualizar cargos do seu município
            if ($user->hasRole('admin')) {
                if (isset($data['municipality_id']) && $data['municipality_id'] !== $user->municipality_id) {
                    abort(403, 'Você não pode transferir cargos para outro município.');
                }
            } else {
                // Outros perfis não podem mudar o município
                if (isset($data['municipality_id']) && $data['municipality_id'] !== $position->municipality_id) {
                    abort(403, 'Você não pode transferir cargos para outro município.');
                }
            }
        }

        $position->update($data);

        return response()->json(new PositionResource($position->fresh()));
    }

    public function destroy(string|int $position): JsonResponse
    {
        $position = Position::query()->findOrFail((int) $position);
        $this->authorize('positions.delete');
        $this->ensurePositionScope($position);

        if ($position->servants()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir um cargo com servidores vinculados.',
            ], 422);
        }

        $position->legislationItems()->detach();
        $position->delete();

        return response()->json(['message' => 'Cargo excluído com sucesso.']);
    }
}
