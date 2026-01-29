<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Legislation;
use Illuminate\Database\Seeder;

class LegislationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = ['Até 200 km', 'Acima 200 km', 'Capital Estado', 'Demais Capitais/DF', 'Exterior'];

        $legislation = Legislation::firstOrCreate(
            ['law_number' => 'Lei Municipal nº 123/2022'],
            [
                'title' => 'ANEXO ÚNICO - Diárias',
                'is_active' => true,
                'destinations' => $destinations,
            ]
        );

        if (! $legislation->destinations) {
            $legislation->update(['destinations' => $destinations]);
        }

        // Valores em centavos (R$ 200,00 = 20000 centavos), keyed by destination label
        $items = [
            [
                'functional_category' => 'Prefeito e Vice-Prefeito',
                'daily_class' => 'Classe A (AP)',
                'values' => ['Até 200 km' => 20000, 'Acima 200 km' => 27000, 'Capital Estado' => 65000, 'Demais Capitais/DF' => 105000, 'Exterior' => 170000],
            ],
            [
                'functional_category' => 'Secretários',
                'daily_class' => 'Classe A (AP)',
                'values' => ['Até 200 km' => 20000, 'Acima 200 km' => 27000, 'Capital Estado' => 65000, 'Demais Capitais/DF' => 105000, 'Exterior' => 170000],
            ],
            [
                'functional_category' => 'Municipais, Tesoureiro, Controlador Interno, Procurador, Subprocurador, Diretor de Contabilidade',
                'daily_class' => 'Classe B (CC-01 a 04A)',
                'values' => ['Até 200 km' => 15000, 'Acima 200 km' => 20000, 'Capital Estado' => 32500, 'Demais Capitais/DF' => 65000, 'Exterior' => 145000],
            ],
            [
                'functional_category' => 'Diretores Escolares e de Departamentos, Assessores Executivos I',
                'daily_class' => 'Classe C (CC-05 a 10)',
                'values' => ['Até 200 km' => 13000, 'Acima 200 km' => 17000, 'Capital Estado' => 30000, 'Demais Capitais/DF' => 52000, 'Exterior' => 145000],
            ],
            [
                'functional_category' => 'Coordenadores, Supervisores, Assessores Técnicos e Executivos II, Ouvidor, Gestor, Chefias de Divisão',
                'daily_class' => 'Classe D (CC-10 a 14)',
                'values' => ['Até 200 km' => 12000, 'Acima 200 km' => 16000, 'Capital Estado' => 26000, 'Demais Capitais/DF' => 45000, 'Exterior' => 145000],
            ],
            [
                'functional_category' => 'Demais Servidores',
                'daily_class' => 'Classe E',
                'values' => ['Até 200 km' => 9000, 'Acima 200 km' => 12000, 'Capital Estado' => 22000, 'Demais Capitais/DF' => 45000, 'Exterior' => 145000],
            ],
        ];

        foreach ($items as $item) {
            $legislation->items()->firstOrCreate(
                [
                    'legislation_id' => $legislation->id,
                    'functional_category' => $item['functional_category'],
                    'daily_class' => $item['daily_class'],
                ],
                $item
            );
        }

        $this->command->info('Legislação ANEXO ÚNICO e itens de diárias criados.');
    }
}
