<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\LegislationItem;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServantSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::orderBy('id')->limit(5)->get();
        $items = LegislationItem::orderBy('id')->get();

        if ($departments->isEmpty() || $items->isEmpty()) {
            $this->command->warn('ServantSeeder: nenhuma secretaria ou item de legislação encontrado. Execute DepartmentSeeder e LegislationSeeder antes.');

            return;
        }

        $requerente = User::where('email', 'requerente@siscondi.gov.br')->first();

        $servidores = [
            [
                'cpf' => '00593959582',
                'matricula' => '235',
                'name' => 'Maria Requerente',
                'rg' => '0991836685',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'requerente@siscondi.gov.br',
                'user_id' => $requerente?->id,
                'legislation_index' => 1,
                'department_index' => 0,
            ],
            [
                'cpf' => '12345678901',
                'matricula' => '100',
                'name' => 'João da Silva',
                'rg' => '1234567',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'joao.silva@exemplo.gov.br',
                'user_id' => null,
                'legislation_index' => 0,
                'department_index' => 0,
            ],
            [
                'cpf' => '23456789012',
                'matricula' => '101',
                'name' => 'Ana Paula Santos',
                'rg' => '2345678',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'ana.santos@exemplo.gov.br',
                'user_id' => null,
                'legislation_index' => 2,
                'department_index' => 1,
            ],
            [
                'cpf' => '34567890123',
                'matricula' => '102',
                'name' => 'Carlos Eduardo Oliveira',
                'rg' => '3456789',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'carlos.oliveira@exemplo.gov.br',
                'user_id' => null,
                'legislation_index' => 3,
                'department_index' => 2,
            ],
            [
                'cpf' => '45678901234',
                'matricula' => '103',
                'name' => 'Fernanda Lima Costa',
                'rg' => '4567890',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'fernanda.lima@exemplo.gov.br',
                'user_id' => null,
                'legislation_index' => 4,
                'department_index' => 1,
            ],
        ];

        foreach ($servidores as $s) {
            $dept = $departments->get(min($s['department_index'], $departments->count() - 1));
            $item = $items->get(min($s['legislation_index'], $items->count() - 1));
            if (! $dept || ! $item) {
                continue;
            }
            Servant::firstOrCreate(
                ['cpf' => $s['cpf']],
                [
                    'user_id' => $s['user_id'],
                    'legislation_item_id' => $item->id,
                    'department_id' => $dept->id,
                    'name' => $s['name'],
                    'rg' => $s['rg'],
                    'organ_expeditor' => $s['organ_expeditor'],
                    'matricula' => $s['matricula'],
                    'bank_name' => 'Banco do Brasil',
                    'agency_number' => '1696-9',
                    'account_number' => '35038-9',
                    'account_type' => 'corrente',
                    'email' => $s['email'],
                    'phone' => null,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Servidores de teste criados.');
    }
}
