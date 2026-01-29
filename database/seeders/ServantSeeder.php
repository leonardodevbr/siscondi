<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Legislation;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServantSeeder extends Seeder
{
    public function run(): void
    {
        $mainDepartment = Department::where('is_main', true)->first();
        $legislation = Legislation::where('code', 'CC-01')->first();

        if (! $mainDepartment || ! $legislation) {
            $this->command->warn('ServantSeeder: secretaria principal ou legislação CC-01 não encontrada. Execute DepartmentSeeder e LegislationSeeder antes.');

            return;
        }

        $requerente = User::where('email', 'requerente@siscondi.gov.br')->first();
        if ($requerente) {
            Servant::firstOrCreate(
                ['cpf' => '00593959582'],
                [
                    'user_id' => $requerente->id,
                    'legislation_id' => $legislation->id,
                    'department_id' => $mainDepartment->id,
                    'name' => 'Maria Requerente',
                    'rg' => '0991836685',
                    'organ_expeditor' => 'SSP/BA',
                    'matricula' => '235',
                    'bank_name' => 'Banco do Brasil',
                    'agency_number' => '1696-9',
                    'account_number' => '35038-9',
                    'account_type' => 'corrente',
                    'email' => 'requerente@siscondi.gov.br',
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Servidor(es) de teste criados.');
    }
}
