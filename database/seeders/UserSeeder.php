<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * SISCONDI - Usuários de Teste
         */
        
        $adminRole = Role::findByName('admin');
        $requesterRole = Role::findByName('requester');
        $validatorRole = Role::findByName('validator');
        $authorizerRole = Role::findByName('authorizer');
        $payerRole = Role::findByName('payer');

        $prefeitura = Branch::where('is_main', true)->first();

        if (! $prefeitura) {
            $this->command->error('Secretaria principal não encontrada.');
            return;
        }

        // Administrador do Sistema
        $admin = User::create([
            'name' => 'Administrador SISCONDI',
            'email' => 'admin@siscondi.gov.br',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole($adminRole);
        $admin->branches()->attach($prefeitura->id, ['is_primary' => true]);

        // Requerente (funcionário que solicita diárias)
        $requester = User::create([
            'name' => 'Maria Requerente',
            'email' => 'requerente@siscondi.gov.br',
            'password' => Hash::make('password'),
        ]);
        $requester->assignRole($requesterRole);
        $requester->branches()->attach($prefeitura->id, ['is_primary' => true]);

        // Validador (Secretário)
        $validator = User::create([
            'name' => 'José Secretário',
            'email' => 'secretario@siscondi.gov.br',
            'password' => Hash::make('password'),
        ]);
        $validator->assignRole($validatorRole);
        $validator->branches()->attach($prefeitura->id, ['is_primary' => true]);

        // Concedente (Prefeito)
        $authorizer = User::create([
            'name' => 'Carlos Prefeito',
            'email' => 'prefeito@siscondi.gov.br',
            'password' => Hash::make('password'),
        ]);
        $authorizer->assignRole($authorizerRole);
        $authorizer->branches()->attach($prefeitura->id, ['is_primary' => true]);

        // Pagador (Tesoureiro)
        $payer = User::create([
            'name' => 'Ana Tesoureira',
            'email' => 'tesoureiro@siscondi.gov.br',
            'password' => Hash::make('password'),
        ]);
        $payer->assignRole($payerRole);
        $payer->branches()->attach($prefeitura->id, ['is_primary' => true]);

        $this->command->info('SISCONDI - Usuários criados:');
        $this->command->info('- Admin: admin@siscondi.gov.br');
        $this->command->info('- Requerente: requerente@siscondi.gov.br');
        $this->command->info('- Validador (Secretário): secretario@siscondi.gov.br');
        $this->command->info('- Concedente (Prefeito): prefeito@siscondi.gov.br');
        $this->command->info('- Pagador (Tesoureiro): tesoureiro@siscondi.gov.br');
        $this->command->info('Senha padrão: password');
    }
}
