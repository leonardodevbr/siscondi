<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DailyRequestStatus;
use App\Http\Requests\StoreDailyRequestRequest;
use App\Http\Requests\UpdateDailyRequestRequest;
use App\Http\Resources\DailyRequestResource;
use App\Models\DailyRequest;
use App\Models\Servant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('daily-requests.view');

        $query = DailyRequest::with([
            'servant.legislation',
            'servant.department',
            'legislationSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer'
        ]);

        // Filtros
        if ($request->has('search')) {
            $search = $request->string('search')->toString();
            $query->whereHas('servant', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->has('servant_id')) {
            $query->where('servant_id', $request->integer('servant_id'));
        }

        if ($request->has('department_id')) {
            $query->whereHas('servant', function ($q) use ($request) {
                $q->where('department_id', $request->integer('department_id'));
            });
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
        $servant = Servant::with('legislation')->findOrFail($request->servant_id);

        $dailyRequest = new DailyRequest($request->validated());
        $dailyRequest->legislation_snapshot_id = $servant->legislation_id;
        $dailyRequest->unit_value = $servant->legislation->daily_value;
        $dailyRequest->status = DailyRequestStatus::DRAFT;
        $dailyRequest->requester_id = auth()->id();
        $dailyRequest->calculateTotal();
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.legislation',
            'servant.department',
            'legislationSnapshot',
            'requester'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest), 201);
    }

    public function show(DailyRequest $dailyRequest): JsonResponse
    {
        $this->authorize('daily-requests.view');

        $dailyRequest->load([
            'servant.legislation',
            'servant.department',
            'legislationSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }

    public function update(UpdateDailyRequestRequest $request, DailyRequest $dailyRequest): JsonResponse
    {
        $dailyRequest->update($request->validated());
        
        if ($request->has('quantity_days')) {
            $dailyRequest->calculateTotal();
            $dailyRequest->save();
        }

        $dailyRequest->load([
            'servant.legislation',
            'servant.department',
            'legislationSnapshot',
            'requester'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }

    public function destroy(DailyRequest $dailyRequest): JsonResponse
    {
        $this->authorize('daily-requests.delete');

        if (!$dailyRequest->isEditable()) {
            return response()->json([
                'message' => 'Não é possível deletar uma solicitação que já foi processada.',
            ], 422);
        }

        $dailyRequest->delete();

        return response()->json(['message' => 'Solicitação deletada com sucesso.']);
    }

    /**
     * Validador (Secretário) valida a solicitação
     */
    public function validate(Request $request, DailyRequest $dailyRequest): JsonResponse
    {
        $this->authorize('daily-requests.validate');

        if (!$dailyRequest->status->canTransitionTo(DailyRequestStatus::VALIDATED)) {
            return response()->json([
                'message' => 'Esta solicitação não pode ser validada no status atual.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::VALIDATED;
        $dailyRequest->validator_id = auth()->id();
        $dailyRequest->validated_at = now();
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.legislation',
            'servant.department',
            'legislationSnapshot',
            'requester',
            'validator'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }

    /**
     * Concedente (Prefeito) autoriza a solicitação
     */
    public function authorize(Request $request, DailyRequest $dailyRequest): JsonResponse
    {
        $this->authorize('daily-requests.authorize');

        if (!$dailyRequest->status->canTransitionTo(DailyRequestStatus::AUTHORIZED)) {
            return response()->json([
                'message' => 'Esta solicitação não pode ser autorizada no status atual.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::AUTHORIZED;
        $dailyRequest->authorizer_id = auth()->id();
        $dailyRequest->authorized_at = now();
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.legislation',
            'servant.department',
            'legislationSnapshot',
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

        if (!$dailyRequest->status->canTransitionTo(DailyRequestStatus::PAID)) {
            return response()->json([
                'message' => 'Esta solicitação não pode ser paga no status atual.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::PAID;
        $dailyRequest->payer_id = auth()->id();
        $dailyRequest->paid_at = now();
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.legislation',
            'servant.department',
            'legislationSnapshot',
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
    public function cancel(Request $request, DailyRequest $dailyRequest): JsonResponse
    {
        $this->authorize('daily-requests.cancel');

        if (!$dailyRequest->isCancellable()) {
            return response()->json([
                'message' => 'Esta solicitação não pode ser cancelada.',
            ], 422);
        }

        $dailyRequest->status = DailyRequestStatus::CANCELLED;
        $dailyRequest->save();

        $dailyRequest->load([
            'servant.legislation',
            'servant.department',
            'legislationSnapshot',
            'requester',
            'validator',
            'authorizer',
            'payer'
        ]);

        return response()->json(new DailyRequestResource($dailyRequest));
    }
}
