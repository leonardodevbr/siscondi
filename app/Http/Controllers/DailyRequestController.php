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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DailyRequestController extends Controller
{
    /**
     * Exige e valida senha/PIN de operação do usuário logado quando ele tem esses dados cadastrados.
     */
    private function ensureSignerOperationCredentials(Request $request): void
    {
        $user = auth()->user();
        if (! $user || ! $user->requiresOperationCredentialsToSign()) {
            return;
        }

        $messages = [];

        if ($user->hasOperationPin()) {
            $pin = trim((string) $request->input('operation_pin', ''));
            $expectedPin = $user->getRawOriginal('operation_pin') ?? $user->attributes['operation_pin'] ?? '';
            if ($pin === '' || $pin !== (string) $expectedPin) {
                $messages['operation_pin'] = ['PIN de autorização incorreto.'];
            }
        }

        if ($user->hasOperationPassword()) {
            $plain = $request->input('operation_password');
            $stored = $user->getRawOriginal('operation_password') ?? $user->operation_password ?? null;
            if ($plain === null || $plain === '' || ! Hash::check($plain, $stored)) {
                $messages['operation_password'] = ['Senha de operação incorreta.'];
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function ensureCanAccess(DailyRequest $dailyRequest): void
    {
        $user = auth()->user();
        
        // Super-admin tem acesso total
        if ($user && $user->hasRole('super-admin')) {
            return;
        }
        
        // Admin do município tem acesso total às solicitações do seu município
        if ($user && $user->hasRole('admin')) {
            $servant = $dailyRequest->servant;
            if ($servant && $servant->department && $servant->department->municipality_id === $user->municipality_id) {
                return;
            }
        }
        
        // Usuário criador pode acessar
        if ($dailyRequest->requester_id === $user->id) {
            return;
        }
        
        // Usuário que é o próprio servidor pode acessar
        if ($user->servant && $user->servant->id === $dailyRequest->servant_id) {
            return;
        }
        
        // Usuário com acesso ao departamento do servidor pode acessar
        $servant = $dailyRequest->servant;
        if ($servant && $user->hasAccessToDepartment($servant->department_id)) {
            return;
        }
        
        abort(403, 'Você não tem permissão para acessar esta solicitação.');
    }

    /**
     * Lista solicitações pendentes de assinatura do usuário atual (validar, conceder ou pagar).
     */
    public function pendingSignatures(Request $request): JsonResponse
    {
        $this->authorize('daily-requests.view');
        
        $user = auth()->user();

        $query = DailyRequest::with(['servant.department', 'requester'])
            ->whereIn('status', [DailyRequestStatus::REQUESTED, DailyRequestStatus::VALIDATED, DailyRequestStatus::AUTHORIZED]);

        // Super-admin vê tudo
        if (!$user->hasRole('super-admin')) {
            // Admin vê tudo do seu município
            if ($user->hasRole('admin') && $user->municipality_id) {
                $query->whereHas('servant.department', function ($q) use ($user): void {
                    $q->where('municipality_id', $user->municipality_id);
                });
            } else {
                // Outros usuários veem apenas das secretarias que têm acesso
                $departmentIds = $user->getDepartmentIds();
                $query->whereHas('servant', function ($q) use ($departmentIds): void {
                    $q->whereIn('department_id', $departmentIds);
                });
            }
        }
        
        $all = $query->orderBy('created_at', 'desc')->get();
        
        // Filtra apenas as que o usuário pode assinar (validar, autorizar ou pagar)
        $filtered = $all->filter(function ($req) use ($user) {
            if ($req->status === DailyRequestStatus::REQUESTED && $user->can('daily-requests.validate')) {
                return true;
            }
            if ($req->status === DailyRequestStatus::VALIDATED && $user->can('daily-requests.authorize')) {
                return true;
            }
            if ($req->status === DailyRequestStatus::AUTHORIZED && $user->can('daily-requests.pay')) {
                return true;
            }
            return false;
        });
        
        return response()->json(DailyRequestResource::collection($filtered->take(20)));
    }

    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $this->authorize('daily-requests.view');
        
        $user = auth()->user();

        $query = DailyRequest::with([
            'servant.legislationItem',
            'servant.department',
            'legislationItemSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer'
        ]);

        // Super-admin vê tudo
        if (!$user->hasRole('super-admin')) {
            // Admin vê tudo do seu município
            if ($user->hasRole('admin') && $user->municipality_id) {
                $query->whereHas('servant.department', function ($q) use ($user): void {
                    $q->where('municipality_id', $user->municipality_id);
                });
            } else {
                // Outros usuários veem apenas das secretarias que têm acesso
                $departmentIds = $user->getDepartmentIds();
                $query->whereHas('servant', function ($q) use ($departmentIds): void {
                    $q->whereIn('department_id', $departmentIds);
                });
            }
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

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;

        if ($request->boolean('all')) {
            return response()->json(DailyRequestResource::collection($query->get()));
        }

        return DailyRequestResource::collection($query->paginate($perPage));
    }

    public function store(StoreDailyRequestRequest $request): JsonResponse
    {
        $this->authorize('daily-requests.create');
        
        $servant = Servant::with('position.legislationItems', 'legislationItem')->findOrFail($request->servant_id);
        $user = auth()->user();

        // Super-admin pode criar para qualquer servidor
        if (!$user->hasRole('super-admin')) {
            // Admin pode criar para servidores do seu município
            if ($user->hasRole('admin')) {
                if ($servant->department && $servant->department->municipality_id !== $user->municipality_id) {
                    return response()->json([
                        'message' => 'Você não tem permissão para criar solicitações para servidores de outros municípios.',
                    ], 403);
                }
            } else {
                // Outros usuários só podem criar para servidores das secretarias que têm acesso
                if (!$user->hasAccessToDepartment($servant->department_id)) {
                    return response()->json([
                        'message' => 'Você não tem permissão para criar solicitações para este servidor.',
                    ], 403);
                }
                
                // Beneficiários (role 'beneficiary') não podem criar solicitações para si mesmos
                if ($user->hasRole('beneficiary') && $user->servant && $user->servant->id === $servant->id) {
                    return response()->json([
                        'message' => 'Beneficiários não podem criar solicitações para si mesmos. Solicite a um requerente autorizado.',
                    ], 403);
                }
            }
        }

        $effectiveItem = $servant->getEffectiveLegislationItem();
        if (! $effectiveItem) {
            return response()->json([
                'message' => 'O servidor selecionado não possui cargo/posição vinculado a um item da legislação com valores de diária. Vincule cargos ao servidor e aos itens da legislação.',
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
            'servant.position',
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
        $this->authorize('daily-requests.edit');
        
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

        $estadoTexto = $municipality?->display_state ?: 'Estado';
        $fundoNome = $department?->fund_name ?: $department?->name ?? '–';
        $cnpjFundo = $department?->fund_cnpj ?: $municipality?->cnpj ?? '–';
        $enderecoSecretaria = $municipality?->address ?? '–';
        $emailSecretaria = $municipality?->email ?? '–';

        $cargoFuncao = $dailyRequest->servant?->position
            ? trim(($dailyRequest->servant->position->symbol ?? '') . ' ' . ($dailyRequest->servant->position->name ?? ''))
            : ($dailyRequest->legislationItemSnapshot?->functional_category ?? '–');

        $baseUrl = rtrim(config('app.url'), '/');

        $logoToBase64 = function (?string $path): ?string {
            if (! $path) {
                return null;
            }
            // Remove leading slashes to ensure relative path for storage disk
            $cleanPath = ltrim($path, '/');
            
            if (! Storage::disk('public')->exists($cleanPath)) {
                return null;
            }
            
            $contents = Storage::disk('public')->get($cleanPath);
            if (! $contents) {
                return null;
            }

            $mime = Storage::disk('public')->mimeType($cleanPath) ?: 'image/png';
            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        };

        $municipalityLogoUrl = null;
        $municipalityLogoData = null;
        if ($municipality?->logo_path && Storage::disk('public')->exists($municipality->logo_path)) {
            $municipalityLogoData = $logoToBase64($municipality->logo_path);
            if (! $municipalityLogoData) {
                $municipalityLogoUrl = $baseUrl . '/storage/' . ltrim($municipality->logo_path, '/');
            }
        }

        $departmentLogoUrl = null;
        $departmentLogoData = null;
        if ($department?->logo_path && Storage::disk('public')->exists($department->logo_path)) {
            $departmentLogoData = $logoToBase64($department->logo_path);
            if (! $departmentLogoData) {
                $departmentLogoUrl = $baseUrl . '/storage/' . ltrim($department->logo_path, '/');
            }
        }

        $requesterSignatureUrl = null;
        $requesterSignatureData = null;
        if ($dailyRequest->requester?->signature_path && Storage::disk('public')->exists($dailyRequest->requester->signature_path)) {
            $requesterSignatureData = $logoToBase64($dailyRequest->requester->signature_path);
            if (! $requesterSignatureData) {
                $requesterSignatureUrl = $baseUrl . '/storage/' . ltrim($dailyRequest->requester->signature_path, '/');
            }
        }

        $validatorSignatureUrl = null;
        $validatorSignatureData = null;
        if ($dailyRequest->validator?->signature_path && Storage::disk('public')->exists($dailyRequest->validator->signature_path)) {
            $validatorSignatureData = $logoToBase64($dailyRequest->validator->signature_path);
            if (! $validatorSignatureData) {
                $validatorSignatureUrl = $baseUrl . '/storage/' . ltrim($dailyRequest->validator->signature_path, '/');
            }
        }

        $authorizerSignatureUrl = null;
        $authorizerSignatureData = null;
        if ($dailyRequest->authorizer?->signature_path && Storage::disk('public')->exists($dailyRequest->authorizer->signature_path)) {
            $authorizerSignatureData = $logoToBase64($dailyRequest->authorizer->signature_path);
            if (! $authorizerSignatureData) {
                $authorizerSignatureUrl = $baseUrl . '/storage/' . ltrim($dailyRequest->authorizer->signature_path, '/');
            }
        }

        $payerSignatureUrl = null;
        $payerSignatureData = null;
        if ($dailyRequest->payer?->signature_path && Storage::disk('public')->exists($dailyRequest->payer->signature_path)) {
            $payerSignatureData = $logoToBase64($dailyRequest->payer->signature_path);
            if (! $payerSignatureData) {
                $payerSignatureUrl = $baseUrl . '/storage/' . ltrim($dailyRequest->payer->signature_path, '/');
            }
        }

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
            'municipality_logo_data' => $municipalityLogoData,
            'department_logo_url' => $departmentLogoUrl,
            'department_logo_data' => $departmentLogoData,
            'requester_signature_url' => $requesterSignatureData,
            'validator_signature_url' => $validatorSignatureData,
            'authorizer_signature_url' => $authorizerSignatureData,
            'payer_signature_url' => $payerSignatureData,
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
            'servant.position',
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
                'servant.cargo',
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
     * Validador valida a solicitação
     */
    public function validate(Request $request, string|int $dailyRequest): JsonResponse
    {
        $this->ensureSignerOperationCredentials($request);

        $dailyRequest = DailyRequest::query()->findOrFail((int) $dailyRequest);
        $this->authorize('daily-requests.validate');
        $this->ensureCanAccess($dailyRequest);

        $dailyRequest->status = DailyRequestStatus::VALIDATED;
        $dailyRequest->validator_id = auth()->id();
        $dailyRequest->validated_at = now();
        $dailyRequest->save();

        DailyRequestLog::logAction(
            $dailyRequest,
            'validated',
            (int) auth()->id(),
            $request->ip(),
            $request->userAgent(),
            ['description' => 'Validado pelo secretário']
        );

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
    public function pay(Request $request, string|int $dailyRequest): JsonResponse
    {
        $this->ensureSignerOperationCredentials($request);

        $dailyRequest = DailyRequest::query()->findOrFail((int) $dailyRequest);
        $this->authorize('daily-requests.validate');
        $this->ensureCanAccess($dailyRequest);

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
            'validated' => 'Validado',
            'authorized' => 'Concedido',
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
        
        $authId = auth()->id();
        
        // Despacha após a resposta para não travar o usuário
        dispatch(function () use ($departmentId, $permission, $dailyRequest, $authId) {
            $users = User::query()
                ->whereHas('departments', fn ($q) => $q->where('departments.id', $departmentId))
                ->get()
                ->filter(fn (User $u) => $u->can($permission));

            foreach ($users as $user) {
                if ($user->id !== $authId) {
                    $user->notify(new DailyRequestPendingNotification($dailyRequest));
                }
            }
        })->afterResponse();
    }
}
