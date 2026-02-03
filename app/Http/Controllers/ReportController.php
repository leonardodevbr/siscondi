<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DailyRequestStatus;
use App\Exports\DailyRequestsReportExport;
use App\Exports\ServantsReportExport;
use App\Models\DailyRequest;
use App\Models\Department;
use App\Models\Servant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /**
     * Relatório de Solicitações de Diárias com filtros avançados
     */
    public function dailyRequestsReport(Request $request): JsonResponse
    {
        $this->authorize('reports.view');

        $user = auth()->user();
        $query = DailyRequest::with([
            'servant.position',
            'servant.department',
            'requester',
            'validator',
            'authorizer',
            'payer',
            'legislationItemSnapshot'
        ]);

        // Escopo por município (admin vê só do seu município)
        if (!$user->hasRole('super-admin')) {
            if ($user->hasRole('admin') && $user->municipality_id) {
                $query->whereHas('servant.department', function ($q) use ($user) {
                    $q->where('municipality_id', $user->municipality_id);
                });
            } else {
                $departmentIds = $user->getDepartmentIds();
                $query->whereHas('servant', function ($q) use ($departmentIds) {
                    $q->whereIn('department_id', $departmentIds);
                });
            }
        }

        // Filtros
        if ($request->filled('department_id')) {
            $query->whereHas('servant', function ($q) use ($request) {
                $q->where('department_id', $request->integer('department_id'));
            });
        }

        if ($request->filled('servant_id')) {
            $query->where('servant_id', $request->integer('servant_id'));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, ['pending', 'validated', 'authorized', 'paid', 'cancelled'])) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->date('end_date'));
        }

        if ($request->filled('departure_start')) {
            $query->whereDate('departure_date', '>=', $request->date('departure_start'));
        }

        if ($request->filled('departure_end')) {
            $query->whereDate('departure_date', '<=', $request->date('departure_end'));
        }

        if ($request->filled('destination_city')) {
            $search = $request->string('destination_city')->toString();
            $query->where('destination_city', 'like', "%{$search}%");
        }

        if ($request->filled('destination_state')) {
            $query->where('destination_state', $request->string('destination_state')->toString());
        }

        $query->orderBy('created_at', 'desc');

        $results = $query->get();

        // Estatísticas
        $stats = [
            'total' => $results->count(),
            'total_value' => $results->sum('total_value'),
            'by_status' => $results->groupBy('status')->map->count(),
            'by_department' => $results->groupBy('servant.department.name')->map->count(),
            'avg_value' => $results->avg('total_value'),
        ];

        return response()->json([
            'data' => $results,
            'stats' => $stats,
        ]);
    }

    /**
     * Relatório de Servidores
     */
    public function servantsReport(Request $request): JsonResponse
    {
        $this->authorize('reports.view');

        $user = auth()->user();
        $query = Servant::with(['department', 'position', 'user']);

        // Escopo por município
        if (!$user->hasRole('super-admin')) {
            if ($user->hasRole('admin') && $user->municipality_id) {
                $query->whereHas('department', function ($q) use ($user) {
                    $q->where('municipality_id', $user->municipality_id);
                });
            } else {
                $departmentIds = $user->getDepartmentIds();
                $query->whereIn('department_id', $departmentIds);
            }
        }

        // Filtros
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('position_id')) {
            $query->where('position_id', $request->integer('position_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%")
                  ->orWhere('matricula', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name', 'asc');

        $results = $query->get();

        // Estatísticas
        $stats = [
            'total' => $results->count(),
            'active' => $results->where('is_active', true)->count(),
            'inactive' => $results->where('is_active', false)->count(),
            'by_department' => $results->groupBy('department.name')->map->count(),
            'by_position' => $results->groupBy('position.name')->map->count(),
        ];

        return response()->json([
            'data' => $results,
            'stats' => $stats,
        ]);
    }

    /**
     * Exportar relatório de diárias para CSV
     */
    public function exportDailyRequestsCsv(Request $request): BinaryFileResponse
    {
        $this->authorize('reports.export');

        $filters = $request->only([
            'department_id', 'servant_id', 'status',
            'start_date', 'end_date', 'departure_start', 'departure_end',
            'destination_city', 'destination_state'
        ]);

        return Excel::download(
            new DailyRequestsReportExport($filters),
            'relatorio-diarias-' . now()->format('Y-m-d-His') . '.csv'
        );
    }

    /**
     * Exportar relatório de diárias para PDF
     */
    public function exportDailyRequestsPdf(Request $request)
    {
        $this->authorize('reports.export');

        $user = auth()->user();
        $query = DailyRequest::with([
            'servant.position',
            'servant.department.municipality',
            'requester',
            'validator',
            'authorizer',
            'legislationItemSnapshot'
        ]);

        // Aplicar filtros (mesmo código do dailyRequestsReport)
        if (!$user->hasRole('super-admin')) {
            if ($user->hasRole('admin') && $user->municipality_id) {
                $query->whereHas('servant.department', function ($q) use ($user) {
                    $q->where('municipality_id', $user->municipality_id);
                });
            } else {
                $departmentIds = $user->getDepartmentIds();
                $query->whereHas('servant', function ($q) use ($departmentIds) {
                    $q->whereIn('department_id', $departmentIds);
                });
            }
        }

        if ($request->filled('department_id')) {
            $query->whereHas('servant', function ($q) use ($request) {
                $q->where('department_id', $request->integer('department_id'));
            });
        }

        if ($request->filled('servant_id')) {
            $query->where('servant_id', $request->integer('servant_id'));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, ['pending', 'validated', 'authorized', 'paid', 'cancelled'])) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->date('end_date'));
        }

        if ($request->filled('departure_start')) {
            $query->whereDate('departure_date', '>=', $request->date('departure_start'));
        }

        if ($request->filled('departure_end')) {
            $query->whereDate('departure_date', '<=', $request->date('departure_end'));
        }

        $query->orderBy('created_at', 'desc');
        $results = $query->get();

        $totalValue = $results->sum('total_value');
        $municipality = $user->municipality;
        $logoVars = $this->getMunicipalityLogoForPdf($municipality);

        $pdf = Pdf::loadView('reports.daily-requests', [
            'requests' => $results,
            'filters' => $request->all(),
            'total_value' => $totalValue,
            'municipality' => $municipality,
            'generated_at' => now(),
        ] + $logoVars);

        $pdf->setPaper('a4', 'landscape');
        return $pdf->stream('relatorio-diarias-' . now()->format('Y-m-d-His') . '.pdf');
    }

    /**
     * Exportar relatório de servidores para CSV
     */
    public function exportServantsCsv(Request $request): BinaryFileResponse
    {
        $this->authorize('reports.export');

        $filters = $request->only([
            'department_id', 'position_id', 'is_active', 'search'
        ]);

        return Excel::download(
            new ServantsReportExport($filters),
            'relatorio-servidores-' . now()->format('Y-m-d-His') . '.csv'
        );
    }

    /**
     * Exportar relatório de servidores para PDF
     */
    public function exportServantsPdf(Request $request)
    {
        $this->authorize('reports.export');

        $user = auth()->user();
        $query = Servant::with(['department.municipality', 'position', 'user']);

        // Escopo por município
        if (!$user->hasRole('super-admin')) {
            if ($user->hasRole('admin') && $user->municipality_id) {
                $query->whereHas('department', function ($q) use ($user) {
                    $q->where('municipality_id', $user->municipality_id);
                });
            } else {
                $departmentIds = $user->getDepartmentIds();
                $query->whereIn('department_id', $departmentIds);
            }
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('position_id')) {
            $query->where('position_id', $request->integer('position_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy('name', 'asc');
        $results = $query->get();

        $municipality = $user->municipality;
        $logoVars = $this->getMunicipalityLogoForPdf($municipality);

        $pdf = Pdf::loadView('reports.servants', [
            'servants' => $results,
            'filters' => $request->all(),
            'municipality' => $municipality,
            'generated_at' => now(),
        ] + $logoVars);

        $pdf->setPaper('a4', 'landscape');
        return $pdf->stream('relatorio-servidores-' . now()->format('Y-m-d-His') . '.pdf');
    }

    /**
     * Retorna municipality_logo_data e municipality_logo_url para o header do PDF de relatório.
     */
    private function getMunicipalityLogoForPdf(?\App\Models\Municipality $municipality): array
    {
        $logoData = null;
        $logoUrl = null;
        if (! $municipality?->logo_path || ! Storage::disk('public')->exists($municipality->logo_path)) {
            return ['municipality_logo_data' => null, 'municipality_logo_url' => null];
        }
        $baseUrl = rtrim(config('app.url'), '/');
        $cleanPath = ltrim($municipality->logo_path, '/');
        $contents = Storage::disk('public')->get($cleanPath);
        if ($contents) {
            $mime = Storage::disk('public')->mimeType($cleanPath) ?: 'image/png';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode($contents);
        }
        if (! $logoData) {
            $logoUrl = $baseUrl . '/storage/' . $cleanPath;
        }
        return ['municipality_logo_data' => $logoData, 'municipality_logo_url' => $logoUrl];
    }
}
