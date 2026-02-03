<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\DailyRequest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DailyRequestsReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection()
    {
        $user = auth()->user();
        $query = DailyRequest::with([
            'servant.position',
            'servant.department.municipality',
            'requester',
            'validator',
            'authorizer',
            'payer',
            'legislationItemSnapshot'
        ]);

        // Escopo por município
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

        // Aplicar filtros
        if (!empty($this->filters['department_id'])) {
            $query->whereHas('servant', function ($q) {
                $q->where('department_id', $this->filters['department_id']);
            });
        }

        if (!empty($this->filters['servant_id'])) {
            $query->where('servant_id', $this->filters['servant_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['start_date'])) {
            $query->whereDate('created_at', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $query->whereDate('created_at', '<=', $this->filters['end_date']);
        }

        if (!empty($this->filters['departure_start'])) {
            $query->whereDate('departure_date', '>=', $this->filters['departure_start']);
        }

        if (!empty($this->filters['departure_end'])) {
            $query->whereDate('departure_date', '<=', $this->filters['departure_end']);
        }

        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Data Solicitação',
            'Servidor',
            'CPF',
            'Cargo',
            'Secretaria',
            'Município',
            'Destino (Cidade)',
            'Destino (UF)',
            'Data Partida',
            'Data Retorno',
            'Qtd. Diárias',
            'Valor Unitário',
            'Valor Total',
            'Status',
            'Requerente',
            'Validador',
            'Concedente',
            'Pagador',
            'Data Validação',
            'Data Concessão',
            'Data Pagamento',
        ];
    }

    public function map($request): array
    {
        return [
            $request->id,
            $request->created_at?->format('d/m/Y H:i'),
            $request->servant?->name,
            $request->servant?->cpf,
            $request->servant?->position?->name,
            $request->servant?->department?->name,
            $request->servant?->department?->municipality?->name,
            $request->destination_city,
            $request->destination_state,
            $request->departure_date?->format('d/m/Y'),
            $request->return_date?->format('d/m/Y'),
            (float) $request->quantity_days,
            number_format(($request->unit_value ?? 0) / 100, 2, ',', '.'),
            number_format(($request->total_value ?? 0) / 100, 2, ',', '.'),
            $request->status?->label() ?? $request->status,
            $request->requester?->name,
            $request->validator?->name,
            $request->authorizer?->name,
            $request->payer?->name,
            $request->validated_at?->format('d/m/Y H:i'),
            $request->authorized_at?->format('d/m/Y H:i'),
            $request->paid_at?->format('d/m/Y H:i'),
        ];
    }
}
