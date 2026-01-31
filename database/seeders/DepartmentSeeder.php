<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Municipality;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Popula os departamentos/secretarias baseado na Lei nº 001/2025 de Cafarnaum-BA
     * Estrutura completa da Administração Direta Municipal
     */
    public function run(): void
    {
        $municipality = Municipality::query()->first();
        if (! $municipality) {
            $this->command->warn('Nenhum município encontrado. Execute a migration de municipalities antes.');
            return;
        }

        $this->command->info('Iniciando criação de departamentos/secretarias...');

        // Estrutura conforme Art. 22 da Lei 001/2025
        
        // ========================================
        // I - ÓRGÃOS DE ASSESSORAMENTO IMEDIATO
        // ========================================
        $orgaosAssessoramento = [
            [
                'name' => 'Gabinete do Prefeito',
                'is_main' => true,
                'parent_id' => null,
                'code' => 'GAB',
                'description' => 'Órgão de assessoramento direto ao Prefeito Municipal',
                'total_employees' => 9,
            ],
            [
                'name' => 'Procuradoria Geral do Município',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'PGM',
                'description' => 'Órgão jurídico de representação do município',
                'total_employees' => 4,
            ],
            [
                'name' => 'Controladoria Geral',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'CGM',
                'description' => 'Órgão de controle interno e auditoria',
                'total_employees' => 4,
            ],
            [
                'name' => 'Ouvidoria Geral',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'OUV',
                'description' => 'Órgão de atendimento e recebimento de demandas da população',
                'total_employees' => 1,
            ],
        ];

        // ========================================
        // II - ÓRGÃOS DE NATUREZA INSTRUMENTAL (MEIO)
        // ========================================
        $orgaosMeio = [
            [
                'name' => 'Secretaria Municipal de Administração e Finanças',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SEMAF',
                'description' => 'Gestão administrativa, financeira e tributária do município',
                'total_employees' => 32,
            ],
            [
                'name' => 'Secretaria Municipal de Planejamento e Desenvolvimento Econômico',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SEMPLAD',
                'description' => 'Planejamento municipal e desenvolvimento econômico',
                'total_employees' => 7,
            ],
            [
                'name' => 'Secretaria Municipal de Governo',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SEGOV',
                'description' => 'Articulação política e comunicação governamental',
                'total_employees' => 6,
            ],
            [
                'name' => 'Secretaria Municipal de Relações Institucionais',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SERIN',
                'description' => 'Relações institucionais e articulação com sociedade civil',
                'total_employees' => 7,
            ],
        ];

        // ========================================
        // III - ÓRGÃOS DE NATUREZA FIM
        // ========================================
        $orgaosFim = [
            [
                'name' => 'Secretaria Municipal de Infraestrutura, Serviços Públicos e Transportes',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SEINFRA',
                'description' => 'Obras públicas, urbanismo, limpeza urbana e transportes',
                'total_employees' => 22,
            ],
            [
                'name' => 'Secretaria Municipal de Agricultura, Pecuária e Irrigação',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SEAGRI',
                'description' => 'Desenvolvimento agropecuário e apoio ao produtor rural',
                'total_employees' => 9,
            ],
            [
                'name' => 'Secretaria Municipal de Meio Ambiente',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SEMAM',
                'description' => 'Proteção ambiental e licenciamento',
                'total_employees' => 5,
            ],
            [
                'name' => 'Secretaria Municipal de Desenvolvimento e Assistência Social',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SEDAS',
                'description' => 'Assistência social, programas sociais e habitação',
                'total_employees' => 29,
            ],
            [
                'name' => 'Secretaria Municipal de Educação',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SEMED',
                'description' => 'Educação básica, infantil e gestão escolar',
                'total_employees' => 156,
            ],
            [
                'name' => 'Secretaria Municipal de Saúde',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SESAU',
                'description' => 'Saúde pública, atenção básica e hospitalar',
                'total_employees' => 45,
            ],
            [
                'name' => 'Secretaria Municipal de Cultura, Esportes e Juventude',
                'is_main' => false,
                'parent_id' => null,
                'code' => 'SECULT',
                'description' => 'Cultura, esportes, juventude e turismo',
                'total_employees' => 16,
            ],
        ];

        // Combinar todos os órgãos
        $todosOrgaos = array_merge(
            $orgaosAssessoramento,
            $orgaosMeio,
            $orgaosFim
        );

        $progressBar = $this->command->getOutput()->createProgressBar(count($todosOrgaos));

        foreach ($todosOrgaos as $orgao) {
            Department::firstOrCreate(
                [
                    'name' => $orgao['name'],
                    'municipality_id' => $municipality->id,
                ],
                array_merge($orgao, [
                    'municipality_id' => $municipality->id,
                ])
            );
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();

        // Criar subdepartamentos principais
        $this->createSubdepartments($municipality);

        // Estatísticas
        $this->showStatistics($municipality);
    }

    /**
     * Cria alguns subdepartamentos principais
     */
    private function createSubdepartments(Municipality $municipality): void
    {
        $this->command->info('Criando principais subdepartamentos...');

        // Subdepartamentos da Educação
        $educacao = Department::where('code', 'SEMED')
            ->where('municipality_id', $municipality->id)
            ->first();

        if ($educacao) {
            $subdeptsEducacao = [
                [
                    'name' => 'Departamento Administrativo e Financeiro - Educação',
                    'code' => 'DAF-ED',
                    'description' => 'Gestão administrativa e financeira da educação',
                ],
                [
                    'name' => 'Departamento Pedagógico',
                    'code' => 'DEPED',
                    'description' => 'Coordenação pedagógica e orientação escolar',
                ],
            ];

            foreach ($subdeptsEducacao as $subdept) {
                Department::firstOrCreate(
                    [
                        'name' => $subdept['name'],
                        'municipality_id' => $municipality->id,
                    ],
                    array_merge($subdept, [
                        'municipality_id' => $municipality->id,
                        'parent_id' => $educacao->id,
                        'is_main' => false,
                    ])
                );
            }
        }

        // Subdepartamentos da Saúde
        $saude = Department::where('code', 'SESAU')
            ->where('municipality_id', $municipality->id)
            ->first();

        if ($saude) {
            $subdeptsSaude = [
                [
                    'name' => 'Departamento de Atenção Básica',
                    'code' => 'DAB',
                    'description' => 'Coordenação da atenção primária à saúde',
                ],
                [
                    'name' => 'Departamento Administrativo Hospitalar',
                    'code' => 'DAH',
                    'description' => 'Gestão administrativa hospitalar',
                ],
                [
                    'name' => 'Departamento de Gestão do SUS',
                    'code' => 'DEGSUS',
                    'description' => 'Gestão e regulação do Sistema Único de Saúde',
                ],
            ];

            foreach ($subdeptsSaude as $subdept) {
                Department::firstOrCreate(
                    [
                        'name' => $subdept['name'],
                        'municipality_id' => $municipality->id,
                    ],
                    array_merge($subdept, [
                        'municipality_id' => $municipality->id,
                        'parent_id' => $saude->id,
                        'is_main' => false,
                    ])
                );
            }
        }

        // Subdepartamentos da Assistência Social
        $assistencia = Department::where('code', 'SEDAS')
            ->where('municipality_id', $municipality->id)
            ->first();

        if ($assistencia) {
            $subdeptsAssistencia = [
                [
                    'name' => 'Departamento de Proteção Social Básica',
                    'code' => 'DPSB',
                    'description' => 'Gestão do CRAS e programas de proteção básica',
                ],
                [
                    'name' => 'Departamento de Proteção Social Especial',
                    'code' => 'DPSE',
                    'description' => 'Gestão do CREAS e proteção especial',
                ],
                [
                    'name' => 'Departamento de Direitos Humanos',
                    'code' => 'DDH',
                    'description' => 'Promoção e defesa dos direitos humanos',
                ],
            ];

            foreach ($subdeptsAssistencia as $subdept) {
                Department::firstOrCreate(
                    [
                        'name' => $subdept['name'],
                        'municipality_id' => $municipality->id,
                    ],
                    array_merge($subdept, [
                        'municipality_id' => $municipality->id,
                        'parent_id' => $assistencia->id,
                        'is_main' => false,
                    ])
                );
            }
        }

        $this->command->info('✓ Subdepartamentos principais criados');
    }

    /**
     * Exibe estatísticas dos departamentos criados
     */
    private function showStatistics(Municipality $municipality): void
    {
        $totalDepartments = Department::where('municipality_id', $municipality->id)->count();
        $mainDepartments = Department::where('municipality_id', $municipality->id)
            ->whereNull('parent_id')
            ->count();
        $subDepartments = Department::where('municipality_id', $municipality->id)
            ->whereNotNull('parent_id')
            ->count();
        $totalEmployees = Department::where('municipality_id', $municipality->id)
            ->sum('total_employees');

        $this->command->newLine();
        $this->command->info('✓ Estrutura administrativa criada com sucesso!');
        $this->command->info('✓ Baseado na Lei nº 001/2025 - Cafarnaum-BA');
        
        $this->command->table(
            ['Métrica', 'Valor'],
            [
                ['Total de Órgãos/Secretarias', $totalDepartments],
                ['Órgãos Principais', $mainDepartments],
                ['Subdepartamentos', $subDepartments],
                ['Total de Cargos Comissionados', $totalEmployees ?? 0],
                ['Órgãos de Assessoramento', 4],
                ['Secretarias Meio', 4],
                ['Secretarias Fim', 7],
            ]
        );

        $this->command->newLine();
        $this->command->info('Estrutura organizacional:');
        $this->command->line('├── Órgãos de Assessoramento Imediato (4)');
        $this->command->line('│   ├── Gabinete do Prefeito');
        $this->command->line('│   ├── Procuradoria Geral');
        $this->command->line('│   ├── Controladoria Geral');
        $this->command->line('│   └── Ouvidoria Geral');
        $this->command->line('├── Órgãos de Natureza Instrumental - Meio (4)');
        $this->command->line('│   ├── Administração e Finanças');
        $this->command->line('│   ├── Planejamento e Desenvolvimento Econômico');
        $this->command->line('│   ├── Governo');
        $this->command->line('│   └── Relações Institucionais');
        $this->command->line('└── Órgãos de Natureza Fim (7)');
        $this->command->line('    ├── Infraestrutura, Serviços Públicos e Transportes');
        $this->command->line('    ├── Agricultura, Pecuária e Irrigação');
        $this->command->line('    ├── Meio Ambiente');
        $this->command->line('    ├── Desenvolvimento e Assistência Social');
        $this->command->line('    ├── Educação');
        $this->command->line('    ├── Saúde');
        $this->command->line('    └── Cultura, Esportes e Juventude');
    }
}