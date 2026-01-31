<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Cria usuários do sistema incluindo alguns baseados em servidores reais
     * Super Admin permanece sem servidor vinculado
     */
    public function run(): void
    {
        $this->command->info('Iniciando criação de usuários...');

        // Buscar roles
        $adminRole = Role::findByName('admin');

        // Buscar departamento principal
        $mainDepartment = Department::where('is_main', true)->first();

        if (!$mainDepartment) {
            $this->command->error('Secretaria principal não encontrada. Execute DepartmentSeeder antes.');
            return;
        }

        $municipalityId = $mainDepartment->municipality_id;

        // Função auxiliar para vincular departamento primário
        $attachPrimary = function (User $u) use ($mainDepartment): void {
            if ($u->departments()->count() === 0) {
                $u->departments()->attach($mainDepartment->id, ['is_primary' => true]);
            }
        };

        $progressBar = $this->command->getOutput()->createProgressBar(6);

        // ========================================
        // SUPER ADMIN (SEM SERVIDOR VINCULADO)
        // ========================================
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@siscondi.gov.br'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('123$qweR---'),
                'municipality_id' => $municipalityId,
            ]
        );
        
        if (!$superAdmin->hasRole('super-admin')) {
            $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
            $superAdminRole->syncPermissions(\Spatie\Permission\Models\Permission::all());
            $superAdmin->assignRole($superAdminRole);
        }
        
        $attachPrimary($superAdmin);
        $progressBar->advance();

        // ========================================
        // ADMIN
        // ========================================
        $admin = User::firstOrCreate(
            ['email' => 'admin@siscondi.gov.br'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('123$qweR---'),
                'municipality_id' => $municipalityId,
            ]
        );
        
        if (!$admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }
        
        $attachPrimary($admin);
        $progressBar->advance();

        $progressBar->finish();
        $this->command->newLine(2);

        // ========================================
        // USUÁRIOS ADICIONAIS BASEADOS EM SERVIDORES REAIS
        // ========================================
        $this->command->info('Criando usuários adicionais para servidores chave...');

        $additionalUsers = [
            // Secretário de Saúde
            [
                'name' => 'Antônio Carlos Sena Xavier',
                'email' => 'antonio.xavier@cafarnaum.ba.gov.br',
                'role' => 'validator',
                'department_code' => 'SESAU',
            ],
            // Secretário de Administração
            [
                'name' => 'Miquéias Oliveira Sena',
                'email' => 'miqueas.sena@cafarnaum.ba.gov.br',
                'role' => 'validator',
                'department_code' => 'SEMAF',
            ],
            // Secretário de Infraestrutura
            [
                'name' => 'Jiusepe Frederico Barbosa Colla',
                'email' => 'jiusepe.colla@cafarnaum.ba.gov.br',
                'role' => 'validator',
                'department_code' => 'SEINFRA',
            ],
            // Procurador
            [
                'name' => 'Samuel Pires Brotas',
                'email' => 'samuel.brotas@cafarnaum.ba.gov.br',
                'role' => 'validator',
                'department_code' => 'PGM',
            ],
        ];

        foreach ($additionalUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('123$qweR---'),
                    'municipality_id' => $municipalityId,
                ]
            );

            // Atribuir role
            $role = Role::findByName($userData['role']);
            if ($role && !$user->hasRole($userData['role'])) {
                $user->assignRole($role);
            }

            // Vincular ao departamento específico
            $dept = Department::where('code', $userData['department_code'])
                ->where('municipality_id', $municipalityId)
                ->first();

            if ($dept && $user->departments()->count() === 0) {
                $user->departments()->attach($dept->id, ['is_primary' => true]);
            }
        }

        // Estatísticas
        $totalUsers = User::where('municipality_id', $municipalityId)->count();
        $usersByRole = [];
        
        foreach (['super-admin', 'admin', 'requester', 'validator', 'authorizer', 'payer'] as $roleName) {
            $usersByRole[$roleName] = User::role($roleName)->count();
        }

        $this->command->newLine();
        $this->command->info('✓ Usuários criados com sucesso!');
        $this->command->newLine();
        
        $this->command->table(
            ['Perfil', 'Quantidade'],
            [
                ['Super Admin', $usersByRole['super-admin'] ?? 0],
                ['Admin', $usersByRole['admin'] ?? 0],
                ['TOTAL', $totalUsers],
            ]
        );

        $this->command->newLine();
        $this->command->info('📧 Credenciais de Acesso:');
        $this->command->line('  ┌─────────────────────────────────────────────────────────┐');
        $this->command->line('  │ Super Admin: superadmin@siscondi.gov.br                 │');
        $this->command->line('  │ Admin:       admin@siscondi.gov.br                      │');
        $this->command->line('  │                                                         │');
        $this->command->line('  │ 🔑 Senha padrão para todos: 123$qweR---                 │');
        $this->command->line('  └─────────────────────────────────────────────────────────┘');
        
        $this->command->newLine();
        $this->command->info('ℹ️  Informações:');
        $this->command->line('  • Super Admin: SEM servidor vinculado (conforme solicitado)');
        $this->command->line('  • Demais usuários: baseados em servidores reais');
        $this->command->line('  • Fonte: Decretos de nomeação de Janeiro/2025');
        $this->command->line('  • Município: Cafarnaum-BA');
    }
}