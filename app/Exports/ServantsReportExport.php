<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Servant;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ServantsReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private array $filters = [])
    {
    }

    public function collection()
    {
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

        // Aplicar filtros
        if (!empty($this->filters['department_id'])) {
            $query->where('department_id', $this->filters['department_id']);
        }

        if (!empty($this->filters['position_id'])) {
            $query->where('position_id', $this->filters['position_id']);
        }

        if (isset($this->filters['is_active'])) {
            $query->where('is_active', (bool) $this->filters['is_active']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%")
                  ->orWhere('matricula', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name', 'asc');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nome',
            'CPF',
            'RG',
            'Órgão Expedidor',
            'Matrícula',
            'Cargo',
            'Secretaria',
            'Município',
            'Banco',
            'Agência',
            'Conta',
            'Tipo Conta',
            'E-mail',
            'Telefone',
            'Status',
            'Cadastrado em',
        ];
    }

    public function map($servant): array
    {
        return [
            $servant->id,
            $servant->name,
            $servant->cpf,
            $servant->rg,
            $servant->organ_expeditor,
            $servant->matricula,
            $servant->position?->name,
            $servant->department?->name,
            $servant->department?->municipality?->name,
            $servant->bank_name,
            $servant->agency_number,
            $servant->account_number,
            $servant->account_type,
            $servant->email,
            $servant->phone,
            $servant->is_active ? 'Ativo' : 'Inativo',
            $servant->created_at?->format('d/m/Y H:i'),
        ];
    }
}
