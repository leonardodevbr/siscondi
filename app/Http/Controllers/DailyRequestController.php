<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DailyRequestStatus;
use App\Http\Requests\StoreDailyRequestRequest;
use App\Http\Requests\UpdateDailyRequestRequest;
use App\Http\Resources\DailyRequestResource;
use App\Models\DailyRequest;
use App\Models\DailyRequestLog;
use App\Models\Servant;
use App\Models\User;
use App\Notifications\DailyRequestPendingNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DailyRequestController extends Controller
{
    private function ensureCanAccess(DailyRequest $dailyRequest): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403, 'Não autenticado.');
        }
        if ($user->hasRole('super-admin')) {
            return;
        }
        if ($dailyRequest->servant?->user_id === $user->id) {
            return;
        }
        // Quem pode "Pagar" acessa qualquer solicitação já autorizada (só confirma e finaliza o fluxo)
        if ($user->can('daily-requests.pay') && $dailyRequest->status === DailyRequestStatus::AUTHORIZED) {
            return;
        }
        $departmentIds = $user->getDepartmentIds();
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

    /**
     * Lista solicitações pendentes de assinatura do usuário atual (validar, conceder ou pagar).
     */
    public function pendingSignatures(Request $request): JsonResponse
    {
        $this->authorize('daily-requests.view');

        $user = auth()->user();
        $departmentIds = $user ? $user->getDepartmentIds() : [];

        $canPay = $user->can('daily-requests.pay');

        $query = DailyRequest::with(['servant', 'requester'])
            ->whereIn('status', [DailyRequestStatus::REQUESTED, DailyRequestStatus::VALIDATED, DailyRequestStatus::AUTHORIZED]);

        if (! empty($departmentIds)) {
            $query->where(function ($q) use ($departmentIds, $canPay): void {
                // Quem pode pagar vê todas as autorizadas (qualquer secretaria)
                if ($canPay) {
                    $q->where('status', DailyRequestStatus::AUTHORIZED)
                        ->orWhere(function ($sub) use ($departmentIds): void {
                            $sub->whereIn('status', [DailyRequestStatus::REQUESTED, DailyRequestStatus::VALIDATED])
                                ->where(function ($scope) use ($departmentIds): void {
                                    $scope->whereHas('requester', function ($req) use ($departmentIds): void {
                                        $req->whereHas('departments', fn ($d) => $d->whereIn('departments.id', $departmentIds));
                                    })
                                    ->orWhereHas('servant', fn ($s) => $s->whereIn('department_id', $departmentIds));
                                });
                        });
                } else {
                    $q->whereHas('requester', function ($req) use ($departmentIds): void {
                        $req->whereHas('departments', function ($d) use ($departmentIds): void {
                            $d->whereIn('departments.id', $departmentIds);
                        });
                    })
                    ->orWhereHas('servant', function ($s) use ($departmentIds): void {
                        $s->whereIn('department_id', $departmentIds);
                    });
                }
            });
        } elseif ($canPay) {
            // Tesoureiro sem departamento: vê só as autorizadas
            $query->where('status', DailyRequestStatus::AUTHORIZED);
        }

        $all = $query->orderBy('created_at', 'desc')->get();
        $pending = collect();

        foreach ($all as $dr) {
            if ($dr->status === DailyRequestStatus::REQUESTED && $user->can('daily-requests.validate')) {
                $pending->push($dr);
            } elseif ($dr->status === DailyRequestStatus::VALIDATED && $user->can('daily-requests.authorize')) {
                $pending->push($dr);
            } elseif ($dr->status === DailyRequestStatus::AUTHORIZED && $user->can('daily-requests.pay')) {
                $pending->push($dr);
            }
        }

        return response()->json(DailyRequestResource::collection($pending->take(20)));
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

        $userId = $user->id;
        $query->where(function ($q) use ($departmentIds, $userId): void {
            $q->whereHas('servant', fn ($s) => $s->where('user_id', $userId));
            if (! empty($departmentIds)) {
                $q->orWhereHas('requester', function ($req) use ($departmentIds): void {
                    $req->whereHas('departments', function ($d) use ($departmentIds): void {
                        $d->whereIn('departments.id', $departmentIds);
                    });
                })
                ->orWhereHas('servant', function ($s) use ($departmentIds): void {
                    $s->whereIn('department_id', $departmentIds);
                });
            }
        });

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
        $dailyRequest->status = DailyRequestStatus::REQUESTED;
        $dailyRequest->requester_id = auth()->id();
        $dailyRequest->calculateTotal();
        $dailyRequest->save();

        DailyRequestLog::logAction(
            $dailyRequest,
            'requested',
            (int) auth()->id(),
            $request->ip(),
            $request->userAgent(),
            ['description' => 'Solicitação registrada pelo requerente']
        );

        $dailyRequest->load([
            'servant.cargos',
            'servant.department',
            'legislationItemSnapshot',
            'requester'
        ]);

        $this->notifyPendingSigners($dailyRequest, 'daily-requests.validate');

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

    /**
     * Dados para a view do PDF (compartilhado entre pdf e pdfPreview).
     */
    private function getPdfViewData(DailyRequest $dailyRequest): array
    {
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

        $baseUrl = rtrim(config('app.url'), '/');

        $municipalityLogoUrl = null;
        if ($municipality?->logo_path && Storage::disk('public')->exists($municipality->logo_path)) {
            $municipalityLogoUrl = $baseUrl . '/storage/' . ltrim($municipality->logo_path, '/');
        }

        $departmentLogoUrl = null;
        if ($department?->logo_path && Storage::disk('public')->exists($department->logo_path)) {
            $departmentLogoUrl = $baseUrl . '/storage/' . ltrim($department->logo_path, '/');
        }

        $signatureUrlFor = function (?User $u): ?string {
            if (! $u?->signature_path || ! Storage::disk('public')->exists($u->signature_path)) {
                return null;
            }
            return rtrim(config('app.url'), '/') . '/storage/' . ltrim($u->signature_path, '/');
        };

        $cidadeUf = trim(($municipality?->name ?? '') . ' - ' . strtoupper($municipality?->state ?? 'BA'));
        if ($cidadeUf === ' - ') {
            $cidadeUf = '–';
        }
        $dataAutorizacao = $dailyRequest->authorized_at?->format('d/m/Y') ?? $dailyRequest->created_at?->format('d/m/Y') ?? now()->format('d/m/Y');
        $dataPagamento = $dailyRequest->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y');

        return [
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
            'municipality_logo_url' => $municipalityLogoUrl,
            'department_logo_url' => $departmentLogoUrl,
            'requester_signature_url' => $signatureUrlFor($dailyRequest->requester),
            'authorizer_signature_url' => $signatureUrlFor($dailyRequest->authorizer),
            'payer_signature_url' => $signatureUrlFor($dailyRequest->payer),
            'cidade_uf' => $cidadeUf,
            'data_autorizacao' => $dataAutorizacao,
            'data_pagamento' => $dataPagamento,
        ];
    }

    public function pdf(string|int $daily_request): Response
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $daily_request);
        $this->authorize('daily-requests.view');
        $this->ensureCanAccess($dailyRequest);

        if (! $dailyRequest->canGeneratePdf()) {
            abort(403, 'O PDF só pode ser gerado após a assinatura do prefeito (concedente). A solicitação ainda não foi deferida para impressão.');
        }

        $dailyRequest->load([
            'servant.department.municipality',
            'servant.cargos',
            'legislationItemSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer',
        ]);

        $data = $this->getPdfViewData($dailyRequest);
        $pdf = Pdf::loadView('daily-requests.pdf', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('solicitacao-diarias-' . $dailyRequest->id . '.pdf');
    }

    /**
     * Rota web temporária: renderiza a view do PDF em HTML para validação do layout.
     * GET /pdf-preview/daily-request/{id}
     */
    public function pdfPreview(string|int $id)
    {
        $dailyRequest = DailyRequest::query()
            ->with([
                'servant.department.municipality',
                'servant.cargos',
                'legislationItemSnapshot',
                'requester',
                'validator',
                'authorizer',
                'payer',
            ])
            ->findOrFail((int) $id);

        $data = $this->getPdfViewData($dailyRequest);

        return view('daily-requests.pdf', $data);
    }

    /**
     * Validador (Secretário) valida a solicitação
     */
    public function validate(Request $request, string|int $dailyRequest): JsonResponse
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $dailyRequest);
        $this->authorize('daily-requests.validate');
        $this->ensureCanAccess($dailyRequest);

        $status = $dailyRequest->status ?? DailyRequestStatus::tryFrom($dailyRequest->getRawOriginal('status'));
        if ($status === null || ! $status->canTransitionTo(DailyRequestStatus::VALIDATED)) {
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

        $this->notifyPendingSigners($dailyRequest, 'daily-requests.authorize');

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

        $status = $dailyRequest->status ?? DailyRequestStatus::tryFrom($dailyRequest->getRawOriginal('status'));
        if ($status === null || ! $status->canTransitionTo(DailyRequestStatus::AUTHORIZED)) {
            return response()->json([
                'message' => 'Esta solicitação não pode ser autorizada no status atual.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::AUTHORIZED;
        $dailyRequest->authorizer_id = auth()->id();
        $dailyRequest->authorized_at = now();
        $dailyRequest->save();

        DailyRequestLog::logAction(
            $dailyRequest,
            'authorized',
            (int) auth()->id(),
            $request->ip(),
            $request->userAgent(),
            ['description' => 'Concedido pelo prefeito – PDF liberado para impressão']
        );

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

        $status = $dailyRequest->status;
        if ($status === null || ! $status instanceof DailyRequestStatus) {
            $rawStatus = $dailyRequest->getRawOriginal('status');
            $status = is_string($rawStatus) ? DailyRequestStatus::tryFrom($rawStatus) : null;
        }
        if ($status === null && $dailyRequest->authorized_at) {
            $dailyRequest->status = DailyRequestStatus::AUTHORIZED;
            $dailyRequest->save();
            $status = DailyRequestStatus::AUTHORIZED;
        }
        if ($status === null || ! $status->canTransitionTo(DailyRequestStatus::PAID)) {
            return response()->json([
                'message' => $status === null
                    ? 'Status da solicitação inválido ou inexistente. Verifique se a solicitação foi concedida pelo prefeito.'
                    : 'Esta solicitação não pode ser paga no status atual.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::PAID;
        $dailyRequest->payer_id = auth()->id();
        $dailyRequest->paid_at = now();
        $dailyRequest->save();

        DailyRequestLog::logAction(
            $dailyRequest,
            'paid',
            (int) auth()->id(),
            $request->ip(),
            $request->userAgent(),
            ['description' => 'Pagamento registrado pela tesouraria']
        );

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

        DailyRequestLog::logAction(
            $dailyRequest,
            'cancelled',
            (int) auth()->id(),
            $request->ip(),
            $request->userAgent(),
            ['description' => 'Indeferido/Cancelado']
        );

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
     * Linha do tempo da solicitação (auditoria) – disponível para todos os envolvidos.
     */
    public function timeline(string|int $daily_request): JsonResponse
    {
        $dailyRequest = DailyRequest::query()->findOrFail((int) $daily_request);
        $this->authorize('daily-requests.view');
        $this->ensureCanAccess($dailyRequest);

        $logs = $dailyRequest->logs()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => $this->actionLabel($log->action),
                'user_id' => $log->user_id,
                'user_name' => $log->user?->name,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'requested' => 'Solicitação registrada',
            'validated' => 'Validado (Secretário)',
            'authorized' => 'Concedido (Prefeito)',
            'paid' => 'Pagamento registrado',
            'cancelled' => 'Indeferido/Cancelado',
            default => $action,
        };
    }

    private function notifyPendingSigners(DailyRequest $dailyRequest, string $permission): void
    {
        $departmentId = $dailyRequest->servant?->department_id;
        if ($departmentId === null) {
            return;
        }
        $users = User::query()
            ->whereHas('departments', fn ($q) => $q->where('departments.id', $departmentId))
            ->get()
            ->filter(fn (User $u) => $u->can($permission));
        foreach ($users as $user) {
            if ($user->id !== auth()->id()) {
                $user->notify(new DailyRequestPendingNotification($dailyRequest));
            }
        }
    }
}
