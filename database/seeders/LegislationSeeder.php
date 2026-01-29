<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Legislation;
use Illuminate\Database\Seeder;

class LegislationSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'CC-01', 'title' => 'Secretário Municipal', 'law_number' => 'Lei Municipal nº 123/2022', 'daily_value' => 325.00],
            ['code' => 'CC-05', 'title' => 'Diretor de Departamento', 'law_number' => 'Lei Municipal nº 123/2022', 'daily_value' => 260.00],
            ['code' => 'CC-10', 'title' => 'Coordenador', 'law_number' => 'Lei Municipal nº 123/2022', 'daily_value' => 200.00],
            ['code' => 'CC-14', 'title' => 'Demais Servidores', 'law_number' => 'Lei Municipal nº 123/2022', 'daily_value' => 150.00],
        ];

        foreach ($items as $item) {
            Legislation::firstOrCreate(
                ['code' => $item['code']],
                array_merge($item, ['is_active' => true])
            );
        }

        $this->command->info('Legislações (cargos/valores de diária) criadas.');
    }
}
