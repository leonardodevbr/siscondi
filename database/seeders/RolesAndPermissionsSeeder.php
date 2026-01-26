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
        // Permissões do sistema
        $permissions = [
            // Usuários
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            
            // Produtos
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'products.import',
            'products.update', // Para ajustes de estoque via produtos
            
            // Estoque
            'stock.view',
            'stock.entry',
            'stock.adjust',
            
            // PDV
            'pos.access',
            'pos.discount',
            
            // Financeiro
            'financial.view',
            'financial.manage',
            
            // Relatórios
            'reports.view',
            'reports.export',
            
            // Marketing
            'marketing.manage',
            'marketing.coupons',
            
            // Clientes
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            
            // Fornecedores
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete',
            
            // Categorias
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
            
            // Filiais
            'branches.view',
            'branches.create',
            'branches.edit',
            'branches.delete',
            
            // Despesas
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.delete',
            'expenses.pay',
            
            // Vendas
            'sales.view',
            'sales.create',
            'sales.edit',
            'sales.cancel',
            
            // Configurações (CRÍTICAS - apenas super-admin)
            'settings.manage',
            'settings.system',
            'settings.integrations',
            'settings.permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ========================================
        // ROLE: SELLER (Vendedor)
        // ========================================
        $sellerRole = Role::firstOrCreate(['name' => 'seller']);
        $sellerRole->syncPermissions([
            'pos.access',
            'products.view',
            'stock.view',
            'customers.view',
            'sales.view',
        ]);

        // ========================================
        // ROLE: STOCKIST (Estoquista)
        // ========================================
        $stockistRole = Role::firstOrCreate(['name' => 'stockist']);
        $stockistRole->syncPermissions([
            'products.view',
            'products.edit',
            'stock.view',
            'stock.entry',
            'stock.adjust',
            'suppliers.view',
        ]);

        // ========================================
        // ROLE: MANAGER (Gerente)
        // Tem TODAS as permissões, EXCETO as críticas de configuração
        // ========================================
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $managerRole->syncPermissions([
            // Usuários (completo)
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            
            // Produtos (completo)
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'products.import',
            'products.update',
            
            // Estoque (completo)
            'stock.view',
            'stock.entry',
            'stock.adjust',
            
            // PDV (completo)
            'pos.access',
            'pos.discount',
            
            // Financeiro (completo)
            'financial.view',
            'financial.manage',
            
            // Relatórios (completo)
            'reports.view',
            'reports.export',
            
            // Marketing (completo)
            'marketing.manage',
            'marketing.coupons',
            
            // Clientes (completo)
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            
            // Fornecedores (completo)
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete',
            
            // Categorias (completo)
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',
            
            // Filiais (completo)
            'branches.view',
            'branches.create',
            'branches.edit',
            'branches.delete',
            
            // Despesas (completo)
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.delete',
            'expenses.pay',
            
            // Vendas (completo)
            'sales.view',
            'sales.create',
            'sales.edit',
            'sales.cancel',
            
            // Configurações básicas (NÃO críticas)
            'settings.manage',
            
            // ❌ NÃO TEM: Configurações críticas (apenas super-admin)
            // 'settings.system',
            // 'settings.integrations',
            // 'settings.permissions',
        ]);

        // ========================================
        // ROLE: SUPER-ADMIN
        // Tem TODAS as permissões via Gate (bypass no AppServiceProvider)
        // ========================================
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        // Super-admin não precisa de permissões específicas, 
        // pois tem acesso total via Gate::before() no AppServiceProvider
    }
}
