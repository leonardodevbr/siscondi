<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Department;
use App\Models\LegislationItem;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServantSeeder extends Seeder
{
    /**
     * Popula servidores baseado nas nomeações reais de Cafarnaum-BA
     * Decretos de Janeiro/2025 - Diário Oficial do Município
     */
    public function run(): void
    {
        $this->command->info('Iniciando criação de servidores...');

        // Buscar dados necessários
        $departments = Department::all();
        $items = LegislationItem::orderBy('id')->get();
        $cargos = Cargo::all();

        if ($departments->isEmpty()) {
            $this->command->warn('ServantSeeder: nenhuma secretaria encontrada. Execute DepartmentSeeder antes.');
            return;
        }

        if ($items->isEmpty()) {
            $this->command->warn('ServantSeeder: nenhum item de legislação encontrado. Execute LegislationSeeder antes.');
            return;
        }

        // Buscar usuários já criados
        $userRequerente = User::where('email', 'requerente@siscondi.gov.br')->first();
        $userSecretario = User::where('email', 'secretario@siscondi.gov.br')->first();
        $userPrefeito = User::where('email', 'prefeito@siscondi.gov.br')->first();
        $userTesoureiro = User::where('email', 'tesoureiro@siscondi.gov.br')->first();

        // Função auxiliar para buscar cargo por símbolo
        $getCargo = function($symbol) use ($cargos) {
            return $cargos->firstWhere('symbol', $symbol);
        };

        // Função auxiliar para buscar departamento por código
        $getDepartment = function($code) use ($departments) {
            return $departments->firstWhere('code', $code);
        };

        /**
         * SERVIDORES BASEADOS NAS NOMEAÇÕES REAIS
         * Fonte: Decretos 001/2025 ao 098/2025 - Janeiro/2025
         */
        $servidores = [
            // ========================================
            // SECRETÁRIOS MUNICIPAIS (CC-01)
            // ========================================
            [
                'cpf' => '00000000001', // CPF fictício - ajustar quando disponível
                'matricula' => '001',
                'name' => 'Ariamiro do Nascimento Neto',
                'rg' => '0000001',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'ariamiro.neto@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-01',
                'department_code' => 'SEMED',
                'user_id' => null,
                'decree_number' => '002/2025',
                'decree_date' => '2025-01-07',
            ],
            [
                'cpf' => '00000000002',
                'matricula' => '002',
                'name' => 'Warlley Gonçalves Barreto',
                'rg' => '0000002',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'warlley.barreto@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-01',
                'department_code' => 'SEDAS',
                'user_id' => null,
                'decree_number' => '003/2025',
                'decree_date' => '2025-01-07',
            ],
            [
                'cpf' => '00000000003',
                'matricula' => '003',
                'name' => 'Antônio Carlos Sena Xavier',
                'rg' => '0000003',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'antonio.xavier@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-01',
                'department_code' => 'SESAU',
                'user_id' => null,
                'decree_number' => '004/2025',
                'decree_date' => '2025-01-07',
            ],
            [
                'cpf' => '00000000004',
                'matricula' => '004',
                'name' => 'Miquéias Oliveira Sena',
                'rg' => '0000004',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'miqueas.sena@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-01',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '005/2025',
                'decree_date' => '2025-01-07',
            ],
            [
                'cpf' => '00000000005',
                'matricula' => '005',
                'name' => 'Jiusepe Frederico Barbosa Colla',
                'rg' => '0000005',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'jiusepe.colla@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-01',
                'department_code' => 'SEINFRA',
                'user_id' => null,
                'decree_number' => '006/2025',
                'decree_date' => '2025-01-07',
            ],
            [
                'cpf' => '00000000006',
                'matricula' => '006',
                'name' => 'Sueli Fernandes de Souza Novais',
                'rg' => '0000006',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'sueli.novais@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-01',
                'department_code' => 'SEGOV',
                'user_id' => null,
                'decree_number' => '008/2025',
                'decree_date' => '2025-01-07',
            ],
            [
                'cpf' => '00000000007',
                'matricula' => '007',
                'name' => 'Ademir Lima da Silva',
                'rg' => '0000007',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'ademir.silva@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-01',
                'department_code' => 'SERIN',
                'user_id' => null,
                'decree_number' => '027/2025',
                'decree_date' => '2025-01-13',
            ],

            // ========================================
            // PROCURADORIA (CC-2A)
            // ========================================
            [
                'cpf' => '00000000008',
                'matricula' => '008',
                'name' => 'Samuel Pires Brotas',
                'rg' => '0000008',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'samuel.brotas@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-2A',
                'department_code' => 'PGM',
                'user_id' => null,
                'decree_number' => '017/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000009',
                'matricula' => '009',
                'name' => 'Bruno da Conceição Nascimento',
                'rg' => '0000009',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'bruno.nascimento@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-2A',
                'department_code' => 'PGM',
                'user_id' => null,
                'decree_number' => '018/2025',
                'decree_date' => '2025-01-13',
            ],

            // ========================================
            // TESOURARIA (CC-03)
            // ========================================
            [
                'cpf' => '00000000010',
                'matricula' => '010',
                'name' => 'Tatiane Boaventura Batista',
                'rg' => '0000010',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'tesoureiro@siscondi.gov.br',
                'cargo_symbol' => 'CC-03',
                'department_code' => 'SEMAF',
                'user_id' => $userTesoureiro?->id,
                'decree_number' => '001/2025',
                'decree_date' => '2025-01-06',
            ],
            [
                'cpf' => '00000000011',
                'matricula' => '011',
                'name' => 'Luiz Carlos Marques de Souza',
                'rg' => '0000011',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'luiz.souza@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-03',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '009/2025',
                'decree_date' => '2025-01-08',
            ],

            // ========================================
            // DIRETORES DE DEPARTAMENTO (CC-04)
            // ========================================
            [
                'cpf' => '00000000012',
                'matricula' => '012',
                'name' => 'Eduardo Vasconcelos dos Santos',
                'rg' => '0000012',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'eduardo.santos@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-04',
                'department_code' => 'SEMED',
                'user_id' => null,
                'decree_number' => '031/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000013',
                'matricula' => '013',
                'name' => 'Leonides Novais de Souza',
                'rg' => '0000013',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'leonides.souza@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-04',
                'department_code' => 'SEMED',
                'user_id' => null,
                'decree_number' => '032/2025',
                'decree_date' => '2025-01-13',
            ],

            // ========================================
            // COORDENADORES (CC-06)
            // ========================================
            [
                'cpf' => '00000000014',
                'matricula' => '014',
                'name' => 'Raiane de Araújo Santos da Hora',
                'rg' => '0000014',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'raiane.hora@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-06',
                'department_code' => 'GAB',
                'user_id' => null,
                'decree_number' => '011/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000015',
                'matricula' => '015',
                'name' => 'Joabe de Souza Silva',
                'rg' => '0000015',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'joabe.silva@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-06',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '021/2025',
                'decree_date' => '2025-01-13',
            ],

            // ========================================
            // DIRETORES E ASSESSORES (CC-07)
            // ========================================
            [
                'cpf' => '00000000016',
                'matricula' => '016',
                'name' => 'Leandro Cavalcante Cruz',
                'rg' => '0000016',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'leandro.cruz@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'GAB',
                'user_id' => null,
                'decree_number' => '012/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000017',
                'matricula' => '017',
                'name' => 'Reinaldo Oliveira Barreto',
                'rg' => '0000017',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'reinaldo.barreto@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'GAB',
                'user_id' => null,
                'decree_number' => '013/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000018',
                'matricula' => '018',
                'name' => 'Maquisson Nei dos Santos Alves',
                'rg' => '0000018',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'maquisson.alves@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'OUV',
                'user_id' => null,
                'decree_number' => '019/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000019',
                'matricula' => '019',
                'name' => 'Felipe Boaventura Batista',
                'rg' => '0000019',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'felipe.batista@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '023/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000020',
                'matricula' => '020',
                'name' => 'Rodrigo Silva Pires',
                'rg' => '0000020',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'rodrigo.pires@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '024/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000021',
                'matricula' => '021',
                'name' => 'Cleifersson Carvalho de Oliveira',
                'rg' => '0000021',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'cleifersson.oliveira@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '025/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000022',
                'matricula' => '022',
                'name' => 'Jonatha Maia de Souza',
                'rg' => '0000022',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'jonatha.souza@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '025/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000023',
                'matricula' => '023',
                'name' => 'Gleudiston Oliveira Vitor',
                'rg' => '0000023',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'gleudiston.vitor@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '025/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000024',
                'matricula' => '024',
                'name' => 'Janderson Gonçalves de Souza',
                'rg' => '0000024',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'janderson.souza@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '025/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000025',
                'matricula' => '025',
                'name' => 'Maíra Frazão Guimarães',
                'rg' => '0000025',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'maira.guimaraes@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-07',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '026/2025',
                'decree_date' => '2025-01-13',
            ],

            // ========================================
            // DIRETORES (CC-10)
            // ========================================
            [
                'cpf' => '00000000026',
                'matricula' => '026',
                'name' => 'José Marcos Cavalcante de Morais',
                'rg' => '0000026',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'jose.morais@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-10',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '020/2025',
                'decree_date' => '2025-01-13',
            ],

            // ========================================
            // CHEFES (CC-12)
            // ========================================
            [
                'cpf' => '00000000027',
                'matricula' => '027',
                'name' => 'Rosália Novais dos Santos',
                'rg' => '0000027',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'rosalia.santos@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-12',
                'department_code' => 'SEMAF',
                'user_id' => null,
                'decree_number' => '022/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000028',
                'matricula' => '028',
                'name' => 'Cristiane Silva dos Santos',
                'rg' => '0000028',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'cristiane.santos@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-12',
                'department_code' => 'SEMED',
                'user_id' => null,
                'decree_number' => '097/2025',
                'decree_date' => '2025-01-23',
            ],

            // ========================================
            // SECRETÁRIOS EXECUTIVOS E COORDENADORES (CC-13)
            // ========================================
            [
                'cpf' => '00000000029',
                'matricula' => '029',
                'name' => 'Ervitor Soares Santana Seixas',
                'rg' => '0000029',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'ervitor.seixas@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-13',
                'department_code' => 'GAB',
                'user_id' => null,
                'decree_number' => '014/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000030',
                'matricula' => '030',
                'name' => 'Edivan Pereira de Novais',
                'rg' => '0000030',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'edivan.novais@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-13',
                'department_code' => 'SEGOV',
                'user_id' => null,
                'decree_number' => '028/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000031',
                'matricula' => '031',
                'name' => 'Moiés Raphael da Silva Archanjo',
                'rg' => '0000031',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'moises.archanjo@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-13',
                'department_code' => 'SEGOV',
                'user_id' => null,
                'decree_number' => '029/2025',
                'decree_date' => '2025-01-13',
            ],

            // ========================================
            // CHEFES E ASSISTENTES (CC-14)
            // ========================================
            [
                'cpf' => '00000000032',
                'matricula' => '032',
                'name' => 'Almiro Carvalho de Almeida',
                'rg' => '0000032',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'almiro.almeida@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-14',
                'department_code' => 'GAB',
                'user_id' => null,
                'decree_number' => '015/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000033',
                'matricula' => '033',
                'name' => 'Eliseu Oliveira Seixas',
                'rg' => '0000033',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'eliseu.seixas@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-14',
                'department_code' => 'GAB',
                'user_id' => null,
                'decree_number' => '016/2025',
                'decree_date' => '2025-01-13',
            ],
            [
                'cpf' => '00000000034',
                'matricula' => '034',
                'name' => 'José Elson de Oliveira de Souza',
                'rg' => '0000034',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'jose.souza@cafarnaum.ba.gov.br',
                'cargo_symbol' => 'CC-14',
                'department_code' => 'SEINFRA',
                'user_id' => null,
                'decree_number' => '098/2025',
                'decree_date' => '2025-01-23',
            ],

            // ========================================
            // USUÁRIO REQUERENTE (exemplo mantido)
            // ========================================
            [
                'cpf' => '00593959582',
                'matricula' => '235',
                'name' => 'Maria Requerente',
                'rg' => '0991836685',
                'organ_expeditor' => 'SSP/BA',
                'email' => 'requerente@siscondi.gov.br',
                'cargo_symbol' => 'CC-13',
                'department_code' => 'SEMED',
                'user_id' => $userRequerente?->id,
                'decree_number' => null,
                'decree_date' => null,
            ],
        ];

        $progressBar = $this->command->getOutput()->createProgressBar(count($servidores));

        foreach ($servidores as $s) {
            $cargo = $getCargo($s['cargo_symbol']);
            $dept = $getDepartment($s['department_code']);
            $item = $items->first(); // Pode ajustar lógica aqui

            if (!$cargo) {
                $this->command->warn("Cargo {$s['cargo_symbol']} não encontrado para {$s['name']}");
                $progressBar->advance();
                continue;
            }

            if (!$dept) {
                $this->command->warn("Departamento {$s['department_code']} não encontrado para {$s['name']}");
                $progressBar->advance();
                continue;
            }

            Servant::firstOrCreate(
                ['cpf' => $s['cpf']],
                [
                    'user_id' => $s['user_id'],
                    'legislation_item_id' => $item?->id,
                    'department_id' => $dept->id,
                    'cargo_id' => $cargo->id,
                    'name' => $s['name'],
                    'rg' => $s['rg'],
                    'organ_expeditor' => $s['organ_expeditor'],
                    'matricula' => $s['matricula'],
                    'bank_name' => 'Banco do Brasil',
                    'agency_number' => '1696-9',
                    'account_number' => substr($s['matricula'], -5) . '-' . rand(0, 9),
                    'account_type' => 'corrente',
                    'email' => $s['email'],
                    'phone' => null,
                    'is_active' => true,
                    'appointment_decree' => $s['decree_number'],
                    'appointment_date' => $s['decree_date'],
                ]
            );

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();

        // Estatísticas
        $totalServants = Servant::count();
        $totalWithUser = Servant::whereNotNull('user_id')->count();
        $totalActive = Servant::where('is_active', true)->count();

        $this->command->info('✓ Servidores criados com sucesso!');
        $this->command->table(
            ['Métrica', 'Valor'],
            [
                ['Total de Servidores', $totalServants],
                ['Com Usuário Vinculado', $totalWithUser],
                ['Ativos', $totalActive],
                ['Inativos', $totalServants - $totalActive],
            ]
        );

        $this->command->newLine();
        $this->command->info('Servidores cadastrados baseados em decretos de nomeação oficial');
        $this->command->info('Período: Janeiro/2025');
        $this->command->info('Fonte: Diário Oficial do Município de Cafarnaum-BA');
    }
}