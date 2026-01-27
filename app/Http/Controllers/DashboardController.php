<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardMetricsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetDashboardMetricsAction $getDashboardMetricsAction
    ) {
    }

    /**
     * Get dashboard metrics.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Determina a filial ativa (header X-Branch-ID ou branch_id do usuário)
        $activeBranchId = $request->header('X-Branch-ID') 
            ? (int) $request->header('X-Branch-ID') 
            : $user->branch_id;

        // Super Admin pode ver dados de todas as filiais se não houver branch ativo
        $branchId = null;
        if ($user->hasRole('super-admin')) {
            $branchId = $activeBranchId; // Pode ser null para ver tudo
        } else {
            // Gerentes e operadores sempre veem apenas sua filial
            $branchId = $activeBranchId ?? $user->branch_id;
        }

        $metrics = $this->getDashboardMetricsAction->execute($branchId);

        return response()->json($metrics);
    }
}
