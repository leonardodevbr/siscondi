<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\DailyRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\DailyRequest;
use App\Models\Municipality;
use App\Models\Servant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * API pública do Portal da Transparência (diárias/passagens).
 * Sem autenticação; dados somente de solicitações pagas.
 * Identificação do município: slug (ex: ?slug=cafarnaum) ou municipality_id.
 */
class TransparencyController extends Controller
{
    /**
     * Resolve o município a partir de slug ou municipality_id na request.
     */
    private function resolveMunicipality(Request $request): ?Municipality
    {
        $slug = $request->string('slug')->trim()->toString();
        if ($slug !== '') {
            $m = Municipality::query()->where('slug', $slug)->first();
            if ($m) {
                return $m;
            }
        }
        $municipalityId = $request->integer('municipality_id');
        if ($municipalityId > 0) {
            return Municipality::find($municipalityId);
        }
        return Municipality::query()->orderBy('name')->first();
    }

    /**
     * Configuração do município para o portal (nome, brasão, etc.).
     * GET /api/public/transparency/config?slug=cafarnaum ou ?municipality_id=1
     */
    public function config(Request $request): JsonResponse
    {
        $municipality = $this->resolveMunicipality($request);

        if (! $municipality) {
            return response()->json([
                'municipality' => null,
                'departments' => [],
            ]);
        }

        $logoUrl = $municipality->logo_path
            ? rtrim(config('app.url'), '/') . '/storage/' . ltrim($municipality->logo_path, '/')
            : null;

        $departments = $municipality->departments()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
            ]);

        return response()->json([
            'municipality' => [
                'id' => $municipality->id,
                'slug' => $municipality->slug,
                'name' => $municipality->name,
                'display_name' => $municipality->display_name,
                'state' => $municipality->state,
                'display_state' => $municipality->display_state,
                'logo_url' => $logoUrl,
            ],
            'departments' => $departments,
        ]);
    }

    /**
     * Listagem pública de diárias pagas (portal da transparência).
     * GET /api/public/transparency/daily-allowances
     * Query: slug ou municipality_id, year, month_start, month_end, department_id, destination, servant_id, per_page, page
     */
    public function dailyAllowances(Request $request): JsonResponse
    {
        $municipality = $this->resolveMunicipality($request);
        if (! $municipality) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0, 'per_page' => 15, 'current_page' => 1, 'last_page' => 1],
            ]);
        }

        $municipalityId = $municipality->id;
        $year = $request->integer('year') ?: (int) date('Y');
        $monthStart = $request->integer('month_start') ?: 1;
        $monthEnd = $request->integer('month_end') ?: 12;
        $departmentId = $request->integer('department_id');
        $destination = $request->string('destination')->trim()->toString();
        $servantId = $request->integer('servant_id');
        $servantName = $request->string('servant_name')->trim()->toString();
        $perPage = min(100, max(5, $request->integer('per_page', 15)));

        $query = DailyRequest::query()
            ->where('status', DailyRequestStatus::PAID)
            ->where(function ($q) use ($municipalityId) {
                $q->where('municipality_id', $municipalityId)
                    ->orWhere(fn ($q2) => $q2->whereNull('municipality_id')->whereHas('servant.department', fn ($q3) => $q3->where('municipality_id', $municipalityId)));
            })
            ->with(['servant:id,name,matricula,department_id,position_id', 'servant.department:id,name,code,municipality_id', 'servant.position:id,name,symbol']);

        if ($year) {
            $query->whereYear('paid_at', $year);
        }
        if ($monthStart && $monthEnd) {
            $query->whereMonth('paid_at', '>=', $monthStart)
                ->whereMonth('paid_at', '<=', $monthEnd);
        }
        if ($departmentId > 0) {
            $query->whereHas('servant', fn ($q) => $q->where('department_id', $departmentId));
        }
        if ($destination !== '') {
            $query->where(function ($q) use ($destination) {
                $q->where('destination_city', 'like', '%' . $destination . '%')
                    ->orWhere('destination_state', 'like', '%' . $destination . '%');
            });
        }
        if ($servantId > 0) {
            $query->where('servant_id', $servantId);
        } elseif ($servantName !== '') {
            $query->whereHas('servant', fn ($q) => $q->where('name', 'like', '%' . $servantName . '%'));
        }

        $query->orderBy('paid_at', 'desc')->orderBy('id', 'desc');

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function (DailyRequest $req) {
            $servant = $req->servant;
            $department = $servant?->department;
            $position = $servant?->position;
            $destino = trim(($req->destination_city ?? '') . ' - ' . ($req->destination_state ?? ''));
            if ($destino === ' - ') {
                $destino = $req->destination_type ?? '—';
            }

            return [
                'id' => $req->id,
                'gestao' => $department ? ($department->code ? "{$department->code} - {$department->name}" : $department->name) : '—',
                'servidor' => $servant?->name ?? '—',
                'matricula' => $servant?->matricula ?? '—',
                'cargo' => $position ? trim(($position->symbol ?? '') . ' ' . ($position->name ?? '')) : '—',
                'destino' => $destino,
                'data_inicial' => $req->departure_date?->format('d/m/Y'),
                'data_final' => $req->return_date?->format('d/m/Y'),
                'quant_diarias' => (float) $req->quantity_days,
                'valor_unitario' => (int) $req->unit_value,
                'valor_total' => (int) $req->total_value,
                'historico' => $req->reason ?: $req->purpose,
                'data_liquidacao' => $req->paid_at?->format('d/m/Y'),
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Exporta as diárias listadas (com os mesmos filtros) em PDF.
     * GET /api/public/transparency/daily-allowances/export/pdf?slug=xxx ou municipality_id=
     */
    public function exportPdf(Request $request)
    {
        $municipality = $this->resolveMunicipality($request);
        if (! $municipality) {
            abort(404, 'Município não informado.');
        }
        $municipalityId = $municipality->id;

        $year = $request->integer('year') ?: (int) date('Y');
        $monthStart = $request->integer('month_start') ?: 1;
        $monthEnd = $request->integer('month_end') ?: 12;
        $departmentId = $request->integer('department_id');
        $destination = $request->string('destination')->trim()->toString();
        $servantId = $request->integer('servant_id');
        $servantName = $request->string('servant_name')->trim()->toString();

        $query = DailyRequest::query()
            ->where('status', DailyRequestStatus::PAID)
            ->where(function ($q) use ($municipalityId) {
                $q->where('municipality_id', $municipalityId)
                    ->orWhere(fn ($q2) => $q2->whereNull('municipality_id')->whereHas('servant.department', fn ($q3) => $q3->where('municipality_id', $municipalityId)));
            })
            ->with(['servant:id,name,matricula,department_id,position_id', 'servant.department:id,name,code,municipality_id', 'servant.position:id,name,symbol']);

        if ($year) {
            $query->whereYear('paid_at', $year);
        }
        if ($monthStart && $monthEnd) {
            $query->whereMonth('paid_at', '>=', $monthStart)
                ->whereMonth('paid_at', '<=', $monthEnd);
        }
        if ($departmentId > 0) {
            $query->whereHas('servant', fn ($q) => $q->where('department_id', $departmentId));
        }
        if ($destination !== '') {
            $query->where(function ($q) use ($destination) {
                $q->where('destination_city', 'like', '%' . $destination . '%')
                    ->orWhere('destination_state', 'like', '%' . $destination . '%');
            });
        }
        if ($servantId > 0) {
            $query->where('servant_id', $servantId);
        } elseif ($servantName !== '') {
            $query->whereHas('servant', fn ($q) => $q->where('name', 'like', '%' . $servantName . '%'));
        }

        $query->orderBy('paid_at', 'desc')->orderBy('id', 'desc');
        $requests = $query->limit(5000)->get();

        $items = $requests->map(function (DailyRequest $req) {
            $servant = $req->servant;
            $department = $servant?->department;
            $position = $servant?->position;
            $destino = trim(($req->destination_city ?? '') . ' - ' . ($req->destination_state ?? ''));
            if ($destino === ' - ') {
                $destino = $req->destination_type ?? '—';
            }
            return [
                'gestao' => $department ? ($department->code ? "{$department->code} - {$department->name}" : $department->name) : '—',
                'servidor' => $servant?->name ?? '—',
                'matricula' => $servant?->matricula ?? '—',
                'cargo' => $position ? trim(($position->symbol ?? '') . ' ' . ($position->name ?? '')) : '—',
                'destino' => $destino,
                'data_inicial' => $req->departure_date?->format('d/m/Y'),
                'data_final' => $req->return_date?->format('d/m/Y'),
                'quant_diarias' => (float) $req->quantity_days,
                'valor_unitario' => (int) $req->unit_value,
                'valor_total' => (int) $req->total_value,
                'data_liquidacao' => $req->paid_at?->format('d/m/Y'),
            ];
        });

        $totalValue = $requests->sum('total_value');
        $logoVars = $this->getMunicipalityLogoForPdf($municipality);

        $pdf = Pdf::loadView('transparency.daily-allowances-pdf', [
            'items' => $items,
            'total_value' => $totalValue,
            'municipality' => $municipality,
            'generated_at' => now(),
        ] + $logoVars);

        $pdf->setPaper('a4', 'landscape');
        $filename = 'transparencia-diarias-' . $municipality->id . '-' . now()->format('Y-m-d-His') . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Detalhe de uma diária (portal público) para o modal "Consultar diária".
     * GET /api/public/transparency/daily-allowances/{id}?slug=xxx ou municipality_id=
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $municipality = $this->resolveMunicipality($request);
        if (! $municipality) {
            return response()->json(['message' => 'Município não informado.'], 404);
        }
        $municipalityId = $municipality->id;

        $req = DailyRequest::query()
            ->where('id', (int) $id)
            ->where('status', DailyRequestStatus::PAID)
            ->where(function ($q) use ($municipalityId) {
                $q->where('municipality_id', $municipalityId)
                    ->orWhere(fn ($q2) => $q2->whereNull('municipality_id')->whereHas('servant.department', fn ($q3) => $q3->where('municipality_id', $municipalityId)));
            })
            ->with(['servant:id,name,matricula,department_id,position_id', 'servant.department:id,name,code', 'servant.position:id,name,symbol'])
            ->first();

        if (! $req) {
            return response()->json(['message' => 'Registro não encontrado.'], 404);
        }

        $servant = $req->servant;
        $department = $servant?->department;
        $position = $servant?->position;
        $destino = trim(($req->destination_city ?? '') . ' - ' . ($req->destination_state ?? ''));
        if ($destino === ' - ') {
            $destino = $req->destination_type ?? '—';
        }

        return response()->json([
            'data' => [
                'id' => $req->id,
                'gestao' => $department ? ($department->code ? "{$department->code} - {$department->name}" : $department->name) : '—',
                'servidor' => $servant?->name ?? '—',
                'matricula' => $servant?->matricula ?? '—',
                'cargo' => $position ? trim(($position->symbol ?? '') . ' ' . ($position->name ?? '')) : '—',
                'destino' => $destino,
                'tipo_destino' => $req->destination_type ?? '—',
                'data_inicial' => $req->departure_date?->format('d/m/Y'),
                'data_final' => $req->return_date?->format('d/m/Y'),
                'quant_diarias' => (float) $req->quantity_days,
                'valor_unitario' => (int) $req->unit_value,
                'valor_total' => (int) $req->total_value,
                'finalidade' => $req->purpose ?? '—',
                'motivo' => $req->reason ?? '—',
                'historico' => $req->reason ?: $req->purpose,
                'data_liquidacao' => $req->paid_at?->format('d/m/Y'),
            ],
        ]);
    }

    /**
     * Lista municípios para o seletor do portal (quando multi-município).
     * Inclui slug para montar URL /transparencia/{slug}.
     */
    public function municipalities(): JsonResponse
    {
        $list = Municipality::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'display_name'])
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'slug' => $m->slug,
                'display_name' => $m->display_name,
            ]);

        return response()->json(['data' => $list]);
    }

    /**
     * Lista destinos distintos (para select do filtro).
     * GET /api/public/transparency/destinations?slug=xxx ou municipality_id=
     */
    public function destinations(Request $request): JsonResponse
    {
        $municipality = $this->resolveMunicipality($request);
        if (! $municipality) {
            return response()->json(['data' => []]);
        }
        $municipalityId = $municipality->id;

        $rows = DailyRequest::query()
            ->where('status', DailyRequestStatus::PAID)
            ->where(function ($q) use ($municipalityId) {
                $q->where('municipality_id', $municipalityId)
                    ->orWhere(fn ($q2) => $q2->whereNull('municipality_id')->whereHas('servant.department', fn ($q3) => $q3->where('municipality_id', $municipalityId)));
            })
            ->select('destination_city', 'destination_state', 'destination_type')
            ->distinct()
            ->get();

        $list = [];
        $seen = [];
        foreach ($rows as $r) {
            $destino = trim(($r->destination_city ?? '') . ' - ' . ($r->destination_state ?? ''));
            if ($destino === ' - ') {
                $destino = $r->destination_type ?? '—';
            }
            if ($destino !== '' && $destino !== '—' && ! isset($seen[$destino])) {
                $seen[$destino] = true;
                $list[] = $destino;
            }
        }
        sort($list);

        return response()->json(['data' => array_values($list)]);
    }

    /**
     * Lista servidores com diárias pagas (para select do filtro).
     * GET /api/public/transparency/servants?slug=xxx ou municipality_id=
     */
    public function servants(Request $request): JsonResponse
    {
        $municipality = $this->resolveMunicipality($request);
        if (! $municipality) {
            return response()->json(['data' => []]);
        }
        $municipalityId = $municipality->id;

        $ids = DailyRequest::query()
            ->where('status', DailyRequestStatus::PAID)
            ->where(function ($q) use ($municipalityId) {
                $q->where('municipality_id', $municipalityId)
                    ->orWhere(fn ($q2) => $q2->whereNull('municipality_id')->whereHas('servant.department', fn ($q3) => $q3->where('municipality_id', $municipalityId)));
            })
            ->distinct()
            ->pluck('servant_id');

        $servants = Servant::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'matricula'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'value' => $s->id,
                'label' => $s->matricula ? "{$s->matricula} - {$s->name}" : $s->name,
            ]);

        return response()->json(['data' => $servants->values()->all()]);
    }

    /**
     * Retorna municipality_logo_data e municipality_logo_url para o header do PDF.
     */
    private function getMunicipalityLogoForPdf(?Municipality $municipality): array
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
