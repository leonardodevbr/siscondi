<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\LegislationItem;
use App\Models\Municipality;
use Illuminate\Database\Seeder;

class CargoSeeder extends Seeder
{
    public function run(): void
    {
        $municipality = Municipality::query()->first();
        if (! $municipality) {
            $this->command->warn('CargoSeeder: nenhum município encontrado. Execute as migrations e seeders anteriores.');

            return;
        }

        $items = [
            ['name' => 'Prefeito e Vice-Prefeito', 'symbol' => '101'],
            ['name' => 'Secretários', 'symbol' => '102'],
            ['name' => 'Municipais, Tesoureiro, Controlador', 'symbol' => '201'],
            ['name' => 'Diretores Escolares e de Departamentos', 'symbol' => '301'],
            ['name' => 'Coordenadores e Supervisores', 'symbol' => '401'],
            ['name' => 'Demais Servidores', 'symbol' => '501'],
        ];

        $legislationItems = LegislationItem::query()->orderBy('id')->limit(count($items))->get();

        foreach ($items as $index => $item) {
            $cargo = Cargo::firstOrCreate(
                [
                    'municipality_id' => $municipality->id,
                    'symbol' => $item['symbol'],
                ],
                [
                    'municipality_id' => $municipality->id,
                    'name' => $item['name'],
                    'symbol' => $item['symbol'],
                ]
            );

            $legislationItem = $legislationItems->get($index);
            if ($legislationItem) {
                $cargo->legislationItems()->syncWithoutDetaching([$legislationItem->id]);
            }
        }

        $this->command->info('Cargos de teste criados.');
    }
}
