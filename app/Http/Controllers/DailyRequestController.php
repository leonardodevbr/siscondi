<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DailyRequestStatus;
use App\Http\Requests\StoreDailyRequestRequest;
use App\Http\Requests\UpdateDailyRequestRequest;
use App\Http\Resources\DailyRequestResource;
use App\Models\DailyRequest;
use App\Models\Servant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DailyRequestController extends Controller
{
    private function ensureCanAccess(DailyRequest $dailyRequest): void
    {
        $user = auth()->user();
        if ($user && $user->hasRole('super-admin')) {
            return;
        }
        $departmentIds = $user ? $user->getDepartmentIds() : [];
        if (empty($departmentIds)) {
            abort(403, 'Sem secretarias vinculadas.');
        }
        $requesterInScope = $dailyRequest->requester
            ? $dailyRequest->requester->departments()->whereIn('departments.id', $departmentIds)->exists()
            : false;
        $servantDeptId = $dailyRequest->servant?->department_id;
        $servantInScope = $servantDeptId !== null && in_array((int) $servantDeptId, $departmentIds, true);
        if (! $requesterInScope && ! $servantInScope) {
            abort(403, 'Solicitação fora do seu escopo.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('daily-requests.view');

        $user = auth()->user();
        $departmentIds = $user ? $user->getDepartmentIds() : [];

        $query = DailyRequest::with([
            'servant.legislationItem',
            'servant.department',
            'legislationItemSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer'
        ]);

        // Escopo: secretaria só vê solicitações feitas por ela ou para ela; admin/gestor vê todas do município (já em getDepartmentIds)
        if (! empty($departmentIds)) {
            $query->where(function ($q) use ($departmentIds): void {
                $q->whereHas('requester', function ($req) use ($departmentIds): void {
                    $req->whereHas('departments', function ($d) use ($departmentIds): void {
                        $d->whereIn('departments.id', $departmentIds);
                    });
                })
                ->orWhereHas('servant', function ($s) use ($departmentIds): void {
                    $s->whereIn('department_id', $departmentIds);
                });
            });
        }

        // Filtros
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->whereHas('servant', function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('servant_id')) {
            $query->where('servant_id', $request->integer('servant_id'));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('servant', function ($q) use ($request): void {
                $q->where('department_id', $request->integer('department_id'));
            });
        }

        if ($request->filled('date_from')) {
            $query->where('departure_date', '>=', $request->string('date_from')->toString());
        }
        if ($request->filled('date_to')) {
            $query->where('return_date', '<=', $request->string('date_to')->toString());
        }

        $query->orderBy('created_at', 'desc');

        if ($request->boolean('all') || !$request->has('page')) {
            $dailyRequests = $query->get();
            return response()->json(DailyRequestResource::collection($dailyRequests));
        }

        return response()->json(DailyRequestResource::collection($query->paginate(15)));
    }

    public function store(StoreDailyRequestRequest $request): JsonResponse
    {
        $servant = Servant::with('cargos.legislationItems', 'legislationItem')->findOrFail($request->servant_id);
        $effectiveItem = $servant->getEffectiveLegislationItem();
        if (! $effectiveItem) {
            return response()->json([
                'message' => 'O servidor selecionado não possui cargo vinculado a um item da legislação com valores de diária. Vincule cargos ao servidor e aos itens da legislação.',
            ], 422);
        }

        $dailyRequest = new DailyRequest($request->validated());
        $dailyRequest->legislation_item_snapshot_id = $effectiveItem->id;
        $dailyRequest->unit_value = $effectiveItem->getValueForDestination($request->destination_type);
        $dailyRequest->status = DailyRequestStatus::DRAFT;
        $dailyRequest->requester_id = auth()->id();
        $dailyRequest->calculateTotal();
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.cargos',
            'servant.department',
            'legislationItemSnapshot',
            'requester'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest), 201);
    }

    public function show(string|int $daily_request): JsonResponse
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $daily_request);
        $this->authorize('daily-requests.view');
        $this->ensureCanAccess($dailyRequest);

        $dailyRequest->load([
            'servant.legislationItem',
            'servant.department',
            'legislationItemSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }

    public function update(UpdateDailyRequestRequest $request, string|int $daily_request): JsonResponse
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $daily_request);
        $this->ensureCanAccess($dailyRequest);
        $dailyRequest->update($request->validated());
        
        if ($request->has('quantity_days')) {
            $dailyRequest->calculateTotal();
            $dailyRequest->save();
        }

        $dailyRequest->load([
            'servant.legislationItem',
            'servant.department',
            'legislationItemSnapshot',
            'requester'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }

    public function destroy(string|int $daily_request): JsonResponse
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $daily_request);
        $this->authorize('daily-requests.delete');
        $this->ensureCanAccess($dailyRequest);

        if (! $dailyRequest->isEditable()) {
            return response()->json([
                'message' => 'Não é possível deletar uma solicitação que já foi processada.',
            ], 422);
        }

        $dailyRequest->delete();

        return response()->json(['message' => 'Solicitação deletada com sucesso.']);
    }

    public function pdf(string|int $daily_request): Response
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $daily_request);
        $this->authorize('daily-requests.view');
        $this->ensureCanAccess($dailyRequest);

        $dailyRequest->load([
            'servant.department.municipality',
            'servant.cargos',
            'legislationItemSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer',
        ]);

        $department = $dailyRequest->servant?->department;
        $municipality = $department?->municipality;

        $estadoTexto = $municipality?->state ? 'Estado da ' . ($municipality->state === 'BA' ? 'Bahia' : $municipality->state) : 'Estado';
        $fundoNome = $department?->fund_name ?: $department?->name ?? '–';
        $cnpjFundo = $department?->cnpj ?: $municipality?->cnpj ?? '–';
        $enderecoSecretaria = $municipality?->address ?? '–';
        $emailSecretaria = $municipality?->email ?? '–';

        $cargoFuncao = $dailyRequest->servant?->cargos?->isNotEmpty()
            ? $dailyRequest->servant->cargos->map(fn ($c) => ($c->symbol ?? '') . ' ' . ($c->name ?? ''))->join(', ')
            : ($dailyRequest->legislationItemSnapshot?->functional_category ?? '–');

        $data = [
            'dailyRequest' => $dailyRequest,
            'municipality' => $municipality,
            'department' => $department,
            'estado_texto' => $estadoTexto,
            'fundo_nome' => $fundoNome,
            'cnpj_fundo' => $cnpjFundo,
            'endereco_secretaria' => $enderecoSecretaria,
            'email_secretaria' => $emailSecretaria,
            'cargo_funcao' => trim((string) $cargoFuncao) ?: '–',
            'ano_exercicio' => (string) now()->year,
        ];

        $pdf = Pdf::loadView('daily-requests.pdf', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('solicitacao-diarias-' . $dailyRequest->id . '.pdf');
    }

    /**
     * Validador (Secretário) valida a solicitação
     */
    public function validate(Request $request, string|int $dailyRequest): JsonResponse
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $dailyRequest);
        $this->authorize('daily-requests.validate');
        $this->ensureCanAccess($dailyRequest);

        if (! $dailyRequest->status->canTransitionTo(DailyRequestStatus::VALIDATED)) {
            return response()->json([
                'message' => 'Esta solicitação não pode ser validada no status atual.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::VALIDATED;
        $dailyRequest->validator_id = auth()->id();
        $dailyRequest->validated_at = now();
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.legislationItem',
            'servant.department',
            'legislationItemSnapshot',
            'requester',
            'validator'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }

    /**
     * Concedente (Prefeito) autoriza a solicitação
     */
    public function authorizeRequest(Request $request, string|int $dailyRequest): JsonResponse
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $dailyRequest);
        $this->authorize('daily-requests.authorize');
        $this->ensureCanAccess($dailyRequest);

        if (! $dailyRequest->status->canTransitionTo(DailyRequestStatus::AUTHORIZED)) {
            return response()->json([
                'message' => 'Esta solicitação não pode ser autorizada no status atual.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::AUTHORIZED;
        $dailyRequest->authorizer_id = auth()->id();
        $dailyRequest->authorized_at = now();
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.legislationItem',
            'servant.department',
            'legislationItemSnapshot',
            'requester',
            'validator',
            'authorizer'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }

    /**
     * Pagador (Tesoureiro) efetua o pagamento
     */
    public function pay(Request $request, DailyRequest $dailyRequest): JsonResponse
    {
        $this->authorize('daily-requests.pay');
        $this->ensureCanAccess($dailyRequest);

        if (! $dailyRequest->status->canTransitionTo(DailyRequestStatus::PAID)) {
            return response()->json([
                'message' => 'Esta solicitação não pode ser paga no status atual.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::PAID;
        $dailyRequest->payer_id = auth()->id();
        $dailyRequest->paid_at = now();
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.legislationItem',
            'servant.department',
            'legislationItemSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }

    /**
     * Cancela a solicitação
     */
    public function cancel(Request $request, string|int $dailyRequest): JsonResponse
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $dailyRequest);
        $this->authorize('daily-requests.cancel');
        $this->ensureCanAccess($dailyRequest);

        if (! $dailyRequest->isCancellable()) {
            return response()->json([
                'message' => 'Esta solicitação não pode ser cancelada.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::CANCELLED;
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.legislationItem',
            'servant.department',
            'legislationItemSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }
}
