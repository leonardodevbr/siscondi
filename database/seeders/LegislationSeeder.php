<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Legislation;
use Illuminate\Database\Seeder;

class LegislationSeeder extends Seeder
{
    public function run(): void
    {
        $legislation = Legislation::firstOrCreate(
            ['law_number' => 'Lei Municipal nº 123/2022'],
            [
                'title' => 'ANEXO ÚNICO - Diárias',
                'is_active' => true,
            ]
        );

        // Valores em centavos (R$ 200,00 = 20000 centavos)
        $items = [
            [
                'functional_category' => 'Prefeito e Vice-Prefeito',
                'daily_class' => 'Classe A (AP)',
                'value_up_to_200km' => 20000,
                'value_above_200km' => 27000,
                'value_state_capital' => 65000,
                'value_other_capitals_df' => 105000,
                'value_exterior' => 170000,
            ],
            [
                'functional_category' => 'Secretários',
                'daily_class' => 'Classe A (AP)',
                'value_up_to_200km' => 20000,
                'value_above_200km' => 27000,
                'value_state_capital' => 65000,
                'value_other_capitals_df' => 105000,
                'value_exterior' => 170000,
            ],
            [
                'functional_category' => 'Municipais, Tesoureiro, Controlador Interno, Procurador, Subprocurador, Diretor de Contabilidade',
                'daily_class' => 'Classe B (CC-01 a 04A)',
                'value_up_to_200km' => 15000,
                'value_above_200km' => 20000,
                'value_state_capital' => 32500,
                'value_other_capitals_df' => 65000,
                'value_exterior' => 145000,
            ],
            [
                'functional_category' => 'Diretores Escolares e de Departamentos, Assessores Executivos I',
                'daily_class' => 'Classe C (CC-05 a 10)',
                'value_up_to_200km' => 13000,
                'value_above_200km' => 17000,
                'value_state_capital' => 30000,
                'value_other_capitals_df' => 52000,
                'value_exterior' => 145000,
            ],
            [
                'functional_category' => 'Coordenadores, Supervisores, Assessores Técnicos e Executivos II, Ouvidor, Gestor, Chefias de Divisão',
                'daily_class' => 'Classe D (CC-10 a 14)',
                'value_up_to_200km' => 12000,
                'value_above_200km' => 16000,
                'value_state_capital' => 26000,
                'value_other_capitals_df' => 45000,
                'value_exterior' => 145000,
            ],
            [
                'functional_category' => 'Demais Servidores',
                'daily_class' => 'Classe E',
                'value_up_to_200km' => 9000,
                'value_above_200km' => 12000,
                'value_state_capital' => 22000,
                'value_other_capitals_df' => 45000,
                'value_exterior' => 145000,
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
