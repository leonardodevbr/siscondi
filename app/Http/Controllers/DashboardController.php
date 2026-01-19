<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardMetricsAction;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetDashboardMetricsAction $getDashboardMetricsAction
    ) {
    }

    /**
     * Get dashboard metrics.
     */
    public function __invoke(): JsonResponse
    {
        $metrics = $this->getDashboardMetricsAction->execute();

        return response()->json($metrics);
    }
}
