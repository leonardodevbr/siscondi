<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DailyRequestStatus;
use App\Models\DailyRequest;
use App\Models\Legislation;
use App\Models\Servant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('daily-requests.view');

        $user = auth()->user();

        // Estatísticas gerais
        $stats = [
            'total_servants' => Servant::where('is_active', true)->count(),
            'total_legislations' => Legislation::where('is_active', true)->count(),
            'total_requests' => DailyRequest::count(),
            
            // Solicitações por status
            'requests_by_status' => [
                'draft' => DailyRequest::where('status', DailyRequestStatus::DRAFT)->count(),
                'requested' => DailyRequest::where('status', DailyRequestStatus::REQUESTED)->count(),
                'validated' => DailyRequest::where('status', DailyRequestStatus::VALIDATED)->count(),
                'authorized' => DailyRequest::where('status', DailyRequestStatus::AUTHORIZED)->count(),
                'paid' => DailyRequest::where('status', DailyRequestStatus::PAID)->count(),
                'cancelled' => DailyRequest::where('status', DailyRequestStatus::CANCELLED)->count(),
            ],
            
            // Valores financeiros
            'financial' => [
                'total_authorized' => DailyRequest::where('status', DailyRequestStatus::AUTHORIZED)
                    ->orWhere('status', DailyRequestStatus::PAID)
                    ->sum('total_value'),
                'total_paid' => DailyRequest::where('status', DailyRequestStatus::PAID)
                    ->sum('total_value'),
                'pending_payment' => DailyRequest::where('status', DailyRequestStatus::AUTHORIZED)
                    ->sum('total_value'),
            ],
            
            // Solicitações recentes
            'recent_requests' => DailyRequest::with(['servant', 'legislationSnapshot'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'servant_name' => $request->servant->name,
                        'destination' => $request->destination_city . '/' . $request->destination_state,
                        'total_value' => $request->total_value,
                        'status' => $request->status->value,
                        'status_label' => $request->status->label(),
                        'created_at' => $request->created_at,
                    ];
                }),
        ];

        // Estatísticas específicas por perfil
        if ($user->can('daily-requests.validate')) {
            $stats['pending_validation'] = DailyRequest::where('status', DailyRequestStatus::REQUESTED)->count();
        }

        if ($user->can('daily-requests.authorize')) {
            $stats['pending_authorization'] = DailyRequest::where('status', DailyRequestStatus::VALIDATED)->count();
        }

        if ($user->can('daily-requests.pay')) {
            $stats['pending_payment_count'] = DailyRequest::where('status', DailyRequestStatus::AUTHORIZED)->count();
        }

        return response()->json($stats);
    }
}
