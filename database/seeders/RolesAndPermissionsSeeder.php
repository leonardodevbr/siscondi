<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'stock.view',
            'stock.entry',
            'stock.adjust',
            'pos.access',
            'pos.discount',
            'financial.view',
            'financial.manage',
            'reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $sellerRole = Role::firstOrCreate(['name' => 'seller']);
        $sellerRole->syncPermissions([
            'pos.access',
            'products.view',
            'stock.view',
        ]);

        $stockistRole = Role::firstOrCreate(['name' => 'stockist']);
        $stockistRole->syncPermissions([
            'products.view',
            'stock.view',
            'stock.entry',
            'stock.adjust',
        ]);

        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions([
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'stock.view',
            'stock.entry',
            'stock.adjust',
            'pos.access',
            'pos.discount',
            'financial.view',
            'financial.manage',
            'reports.view',
            'users.view',
            'users.create',
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
    }
}
