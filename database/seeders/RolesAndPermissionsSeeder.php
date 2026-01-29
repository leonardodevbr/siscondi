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
        /**
         * SISCONDI - Sistema de Concessão de Diárias
         * Permissões e Perfis de Acesso
         */
        
        // Permissões do sistema
        $permissions = [
            // Usuários
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            
            // Secretarias (Branches)
            'departments.view',
            'departments.create',
            'departments.edit',
            'departments.delete',
            
            // Legislações (Cargos e Valores)
            'legislations.view',
            'legislations.create',
            'legislations.edit',
            'legislations.delete',
            
            // Cargos (símbolo + pivot com itens da lei)
            'cargos.view',
            'cargos.create',
            'cargos.edit',
            'cargos.delete',
            
            // Servidores
            'servants.view',
            'servants.create',
            'servants.edit',
            'servants.delete',
            
            // Solicitações de Diárias
            'daily-requests.view',
            'daily-requests.create',
            'daily-requests.edit',
            'daily-requests.delete',
            'daily-requests.validate',   // Secretário valida
            'daily-requests.authorize',  // Prefeito concede
            'daily-requests.pay',        // Tesoureiro paga
            'daily-requests.cancel',
            
            // Relatórios
            'reports.view',
            'reports.export',
            
            // Configurações
            'settings.manage',
            'settings.system',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ========================================
        // ROLE: ADMIN (Administrador do Sistema)
        // Acesso total ao sistema
        // ========================================
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::all());

        // ========================================
        // ROLE: REQUESTER (Requerente)
        // Cria e acompanha suas próprias solicitações de diárias
        // ========================================
        $requesterRole = Role::firstOrCreate(['name' => 'requester']);
        $requesterRole->syncPermissions([
            'daily-requests.view',
            'daily-requests.create',
            'daily-requests.edit',
            'servants.view',
            'legislations.view',
            'cargos.view',
            'departments.view',
        ]);

        // ========================================
        // ROLE: VALIDATOR (Validador - Secretário)
        // Valida solicitações de diárias da sua secretaria
        // ========================================
        $validatorRole = Role::firstOrCreate(['name' => 'validator']);
        $validatorRole->syncPermissions([
            'daily-requests.view',
            'daily-requests.validate',
            'servants.view',
            'servants.create',
            'servants.edit',
            'legislations.view',
            'cargos.view',
            'cargos.create',
            'cargos.edit',
            'departments.view',
            'reports.view',
        ]);

        // ========================================
        // ROLE: AUTHORIZER (Concedente - Prefeito)
        // Autoriza/concede diárias validadas pelos secretários
        // ========================================
        $authorizerRole = Role::firstOrCreate(['name' => 'authorizer']);
        $authorizerRole->syncPermissions([
            'daily-requests.view',
            'daily-requests.authorize',
            'servants.view',
            'legislations.view',
            'legislations.create',
            'legislations.edit',
            'cargos.view',
            'cargos.create',
            'cargos.edit',
            'departments.view',
            'departments.create',
            'departments.edit',
            'reports.view',
            'reports.export',
        ]);

        // ========================================
        // ROLE: PAYER (Pagador - Tesoureiro)
        // Efetua o pagamento das diárias autorizadas
        // ========================================
        $payerRole = Role::firstOrCreate(['name' => 'payer']);
        $payerRole->syncPermissions([
            'daily-requests.view',
            'daily-requests.pay',
            'servants.view',
            'legislations.view',
            'cargos.view',
            'departments.view',
            'reports.view',
            'reports.export',
        ]);

        // Super Admin: acesso total (igual admin, para compatibilidade)
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->syncPermissions(Permission::all());
    }
}
