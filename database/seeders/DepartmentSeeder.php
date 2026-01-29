<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Municipality;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $municipality = Municipality::query()->first();
        if (! $municipality) {
            $this->command->warn('Nenhum município encontrado. Execute a migration de municipalities antes.');

            return;
        }

        $secretarias = [
            ['name' => 'Secretaria de Educação', 'is_main' => false],
            ['name' => 'Secretaria de Saúde', 'is_main' => false],
            ['name' => 'Secretaria de Administração', 'is_main' => false],
            ['name' => 'Secretaria de Obras', 'is_main' => false],
            ['name' => 'Secretaria de Assistência Social', 'is_main' => false],
        ];

        foreach ($secretarias as $secretaria) {
            Department::firstOrCreate(
                ['name' => $secretaria['name'], 'municipality_id' => $municipality->id],
                array_merge($secretaria, ['municipality_id' => $municipality->id])
            );
        }

        $this->command->info('Secretarias criadas: Gabinete do Prefeito (principal) + 5 secretarias.');
    }
}
