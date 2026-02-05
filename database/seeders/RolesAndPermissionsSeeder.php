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
         * DiariaSys - Sistema de Concessão de Diárias
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
            
            // Cargos/Posições (símbolo + pivot com itens da lei)
            'positions.view',
            'positions.create',
            'positions.edit',
            'positions.delete',
            
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
            'daily-requests.edit'
        ]);

        // ========================================
        // ROLE: VALIDATOR (Validador - Secretário)
        // Valida solicitações de diárias da sua secretaria
        // ========================================
        $validatorRole = Role::firstOrCreate(['name' => 'validator']);
        $validatorRole->syncPermissions([
            'daily-requests.view',
            'daily-requests.validate',
            'daily-requests.pay',   // Secretário também pode dar baixa (marcar como pago)
            'servants.view',
            'servants.create',
            'servants.edit',
            'legislations.view',
            'legislations.create',
            'legislations.edit',
            'positions.view',
            'positions.create',
            'positions.edit',
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
            'positions.view',
            'positions.create',
            'positions.edit',
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
            'positions.view',
            'departments.view',
            'reports.view',
            'reports.export',
        ]);

        // ========================================
        // ROLE: BENEFICIARY (Beneficiário de diárias)
        // Usuário gerado para um servidor: pode acessar o sistema mas não pode criar
        // solicitações para si mesmo (apenas recebe diárias; outro usuário solicita por ele).
        // ========================================
        $beneficiaryRole = Role::firstOrCreate(['name' => 'beneficiary']);
        $beneficiaryRole->syncPermissions([
            'daily-requests.view',
            'servants.view',
            'departments.view',
        ]);

        // Super Admin: acesso total (igual admin, para compatibilidade)
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->syncPermissions(Permission::all());
    }
}
