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
        $superAdminRole = Role::findByName('super-admin');
        $managerRole = Role::findByName('manager');
        $sellerRole = Role::findByName('seller');

        $matriz = Branch::where('is_main', true)->first();
        $filialShopping = Branch::where('name', 'Filial Shopping')->first();

        if (! $matriz) {
            $this->command->error('Matriz não encontrada. Certifique-se de que a migration de branches foi executada.');
            return;
        }

        if (! $filialShopping) {
            $this->command->error('Filial Shopping não encontrada. Certifique-se de que o BranchSeeder foi executado.');
            return;
        }

        $admin = User::factory()->global()->create([
            'name' => 'Administrador',
            'email' => 'admin@adonai.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole($superAdminRole);

        $gerenteMatriz = User::factory()->forBranch($matriz)->create([
            'name' => 'Gerente Matriz',
            'email' => 'gerente_matriz@adonai.com',
            'password' => Hash::make('password'),
        ]);
        $gerenteMatriz->assignRole($managerRole);

        $vendedorMatriz = User::factory()->forBranch($matriz)->create([
            'name' => 'Vendedor Matriz',
            'email' => 'vendedor_matriz@adonai.com',
            'password' => Hash::make('password'),
        ]);
        $vendedorMatriz->assignRole($sellerRole);

        $gerenteShopping = User::factory()->forBranch($filialShopping)->create([
            'name' => 'Gerente Shopping',
            'email' => 'gerente_shopping@adonai.com',
            'password' => Hash::make('password'),
        ]);
        $gerenteShopping->assignRole($managerRole);

        $vendedorShopping = User::factory()->forBranch($filialShopping)->create([
            'name' => 'Vendedor Shopping',
            'email' => 'vendedor_shopping@adonai.com',
            'password' => Hash::make('password'),
        ]);
        $vendedorShopping->assignRole($sellerRole);

        $this->command->info("Created 5 users: 1 Admin (global), 2 Matriz (ID: {$matriz->id}), 2 Shopping (ID: {$filialShopping->id})");
    }
}
