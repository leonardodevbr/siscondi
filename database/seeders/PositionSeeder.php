<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Popula os cargos (positions) baseado na Lei nº 001/2025 de Cafarnaum-BA
     * Total: 393 cargos em comissão distribuídos em 16 símbolos
     */
    public function run(): void
    {
        $municipality = Municipality::query()->first();
        if (! $municipality) {
            $this->command->warn('PositionSeeder: nenhum município encontrado. Execute as migrations e seeders anteriores.');
            return;
        }

        // Estrutura completa de cargos conforme Lei 001/2025
        $positionsData = [
            ['name' => 'Secretário Municipal', 'symbol' => 'CC-01', 'salary' => null, 'description' => 'Secretários de todas as pastas municipais', 'total_positions' => 15],
            ['name' => 'Procurador Chefe', 'symbol' => 'CC-02', 'salary' => 8000.00, 'description' => 'Procurador Chefe e Controlador Interno', 'total_positions' => 2],
            ['name' => 'Subprocurador', 'symbol' => 'CC-2A', 'salary' => 6800.00, 'description' => 'Subprocurador da Procuradoria Geral', 'total_positions' => 2],
            ['name' => 'Tesoureiro e Diretor de Orçamento', 'symbol' => 'CC-03', 'salary' => 5000.00, 'description' => 'Tesoureiro Municipal e Diretor de Orçamento e Contabilidade', 'total_positions' => 2],
            ['name' => 'Diretor de Departamento - Educação', 'symbol' => 'CC-04', 'salary' => 4600.00, 'description' => 'Diretores de Departamento da Secretaria de Educação', 'total_positions' => 2],
            ['name' => 'Diretor de Departamento', 'symbol' => 'CC-05', 'salary' => 4000.00, 'description' => 'Diretores de Departamentos de diversas secretarias', 'total_positions' => 11],
            ['name' => 'Coordenador e Chefe de Gabinete', 'symbol' => 'CC-06', 'salary' => 3500.00, 'description' => 'Coordenadores de áreas e Chefes de Gabinete', 'total_positions' => 21],
            ['name' => 'Diretor e Assessor Executivo I', 'symbol' => 'CC-07', 'salary' => 3000.00, 'description' => 'Diretores de Departamento, Coordenadores e Assessores', 'total_positions' => 42],
            ['name' => 'Coordenador e Gerente', 'symbol' => 'CC-08', 'salary' => 2800.00, 'description' => 'Coordenadores de programas e Gerentes de área', 'total_positions' => 6],
            ['name' => 'Secretário Executivo de Gabinete', 'symbol' => 'CC-09', 'salary' => 2700.00, 'description' => 'Secretários Executivos e Assistentes de Gabinete', 'total_positions' => 3],
            ['name' => 'Assessor Executivo e Diretor', 'symbol' => 'CC-10', 'salary' => 2500.00, 'description' => 'Assessores Executivos e Diretores de área', 'total_positions' => 23],
            ['name' => 'Diretor e Chefe de Divisão', 'symbol' => 'CC-11', 'salary' => 2400.00, 'description' => 'Diretores e Chefes de Divisão', 'total_positions' => 3],
            ['name' => 'Coordenador e Chefe', 'symbol' => 'CC-12', 'salary' => 2300.00, 'description' => 'Coordenadores, Diretores e Chefes de Divisão', 'total_positions' => 16],
            ['name' => 'Secretário Executivo e Coordenador', 'symbol' => 'CC-13', 'salary' => 2000.00, 'description' => 'Secretários Executivos, Coordenadores e Chefes', 'total_positions' => 46],
            ['name' => 'Assistente e Chefe', 'symbol' => 'CC-14', 'salary' => 1800.00, 'description' => 'Assistentes Técnicos, Chefes de Divisão e Técnicos', 'total_positions' => 86],
            ['name' => 'Motorista e Supervisor', 'symbol' => 'CC-15', 'salary' => 1600.00, 'description' => 'Motoristas de Gabinete, Supervisores e Chefes', 'total_positions' => 32],
            ['name' => 'Cargos da Educação', 'symbol' => 'LEI-EDU', 'salary' => null, 'description' => 'Diretores, Vice-Diretores e Coordenadores Pedagógicos conforme Lei 134/2024', 'total_positions' => 81],
            ['name' => 'Prefeito Municipal', 'symbol' => 'CC-001', 'salary' => null, 'description' => 'Prefeito Municipal', 'total_positions' => 1],
            ['name' => 'Vice-Prefeito Municipal', 'symbol' => 'CC-002', 'salary' => null, 'description' => 'Vice-Prefeito Municipal', 'total_positions' => 1],
        ];

        $this->command->info('Iniciando criação de cargos (positions)...');
        $progressBar = $this->command->getOutput()->createProgressBar(count($positionsData));

        foreach ($positionsData as $data) {
            Position::firstOrCreate(
                [
                    'municipality_id' => $municipality->id,
                    'symbol' => $data['symbol'],
                ],
                [
                    'municipality_id' => $municipality->id,
                    'name' => $data['name'],
                    'symbol' => $data['symbol'],
                    'salary' => $data['salary'],
                    'description' => $data['description'],
                    'total_positions' => $data['total_positions'],
                ]
            );
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info('✓ Total de ' . count($positionsData) . ' categorias de cargos criadas.');
        $this->command->info('✓ Baseado na Lei nº 001/2025 - Cafarnaum-BA');

        $totalCargos = Position::where('municipality_id', $municipality->id)->count();
        $totalPosicoes = Position::where('municipality_id', $municipality->id)->sum('total_positions');
        $this->command->table(
            ['Métrica', 'Valor'],
            [
                ['Categorias de Cargos', $totalCargos],
                ['Total de Posições', $totalPosicoes],
                ['Maior Salário', 'R$ 8.000,00 (CC-02)'],
                ['Menor Salário', 'R$ 1.600,00 (CC-15)'],
            ]
        );
    }
}
