<?php

declare(strict_types=1);

namespace Database\Seeders;

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
        $stockistRole = Role::findByName('stockist');

        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
        ]);
        $superAdmin->assignRole($superAdminRole);

        $manager = User::factory()->create([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole($managerRole);

        $seller = User::factory()->create([
            'name' => 'Seller',
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);
        $seller->assignRole($sellerRole);

        $stockist = User::factory()->create([
            'name' => 'Stockist',
            'email' => 'stockist@test.com',
            'password' => Hash::make('password'),
        ]);
        $stockist->assignRole($stockistRole);

        User::factory(6)->create()->each(function (User $user): void {
            $roles = Role::all()->random(rand(0, 2));
            if ($roles->isNotEmpty()) {
                $user->assignRole($roles);
            }
        });
    }
}
