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
    }
}