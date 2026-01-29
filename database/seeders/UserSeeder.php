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
    public function run(): void
    {
        $adminRole = Role::findByName('admin');
        $requesterRole = Role::findByName('requester');
        $validatorRole = Role::findByName('validator');
        $authorizerRole = Role::findByName('authorizer');
        $payerRole = Role::findByName('payer');

        $mainDepartment = Department::where('is_main', true)->first();

        if (! $mainDepartment) {
            $this->command->error('Secretaria principal não encontrada. Execute DepartmentSeeder ou a migration de departments.');

            return;
        }

        $municipalityId = $mainDepartment->municipality_id;

        $attachPrimary = function (User $u) use ($mainDepartment): void {
            $u->departments()->attach($mainDepartment->id, ['is_primary' => true]);
        };

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@siscondi.gov.br'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('password'),
                'municipality_id' => $municipalityId,
            ]
        );
        if (! $superAdmin->hasRole('super-admin')) {
            $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
            $superAdminRole->syncPermissions(\Spatie\Permission\Models\Permission::all());
            $superAdmin->assignRole($superAdminRole);
        }
        if ($superAdmin->departments()->count() === 0) {
            $superAdmin->departments()->attach($mainDepartment->id, ['is_primary' => true]);
        }

        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@siscondi.gov.br',
            'password' => Hash::make('password'),
            'municipality_id' => $municipalityId,
        ]);
        $admin->assignRole($adminRole);
        $attachPrimary($admin);

        $requester = User::create([
            'name' => 'Maria Requerente',
            'email' => 'requerente@siscondi.gov.br',
            'password' => Hash::make('password'),
            'municipality_id' => $municipalityId,
        ]);
        $requester->assignRole($requesterRole);
        $attachPrimary($requester);

        $validator = User::create([
            'name' => 'José Secretário',
            'email' => 'secretario@siscondi.gov.br',
            'password' => Hash::make('password'),
            'municipality_id' => $municipalityId,
        ]);
        $validator->assignRole($validatorRole);
        $attachPrimary($validator);

        $authorizer = User::create([
            'name' => 'Carlos Prefeito',
            'email' => 'prefeito@siscondi.gov.br',
            'password' => Hash::make('password'),
            'municipality_id' => $municipalityId,
        ]);
        $authorizer->assignRole($authorizerRole);
        $attachPrimary($authorizer);

        $payer = User::create([
            'name' => 'Ana Tesoureira',
            'email' => 'tesoureiro@siscondi.gov.br',
            'password' => Hash::make('password'),
            'municipality_id' => $municipalityId,
        ]);
        $payer->assignRole($payerRole);
        $attachPrimary($payer);

        $this->command->info('Usuários criados. Senha padrão: password');
        $this->command->info('  Super Admin: superadmin@siscondi.gov.br');
        $this->command->info('  Admin: admin@siscondi.gov.br');
        $this->command->info('  Requerente: requerente@siscondi.gov.br');
        $this->command->info('  Validador: secretario@siscondi.gov.br');
        $this->command->info('  Concedente: prefeito@siscondi.gov.br');
        $this->command->info('  Pagador: tesoureiro@siscondi.gov.br');
    }
}
