<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DiagnoseUserPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:diagnose {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnostica permissões de um usuário específico';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        
        $user = User::where('email', $email)->first();
        
        if (! $user) {
            $this->error("Usuário com email '{$email}' não encontrado.");
            return 1;
        }
        
        $this->info("=== DIAGNÓSTICO DO USUÁRIO ===");
        $this->info("ID: {$user->id}");
        $this->info("Nome: {$user->name}");
        $this->info("Email: {$user->email}");
        $this->info("Branch ID: {$user->branch_id}");
        $this->newLine();
        
        $this->info("=== ROLES (PAPÉIS) ===");
        $roles = $user->getRoleNames();
        if ($roles->isEmpty()) {
            $this->warn("⚠️  Usuário NÃO tem nenhum role atribuído!");
        } else {
            foreach ($roles as $role) {
                $this->line("✓ {$role}");
            }
        }
        $this->newLine();
        
        $this->info("=== PERMISSÕES DIRETAS ===");
        $directPermissions = $user->getDirectPermissions();
        if ($directPermissions->isEmpty()) {
            $this->line("Nenhuma permissão direta.");
        } else {
            foreach ($directPermissions as $permission) {
                $this->line("✓ {$permission->name}");
            }
        }
        $this->newLine();
        
        $this->info("=== TODAS AS PERMISSÕES (via roles + diretas) ===");
        $allPermissions = $user->getAllPermissions();
        if ($allPermissions->isEmpty()) {
            $this->error("⚠️  Usuário NÃO tem nenhuma permissão!");
        } else {
            foreach ($allPermissions as $permission) {
                $this->line("✓ {$permission->name}");
            }
        }
        $this->newLine();
        
        $this->info("=== VERIFICAÇÕES ESPECÍFICAS ===");
        $criticalPermissions = [
            'pos.access',
            'pos.discount',
            'products.view',
            'stock.view',
            'stock.entry',
            'stock.adjust',
        ];
        
        foreach ($criticalPermissions as $permission) {
            $has = $user->hasPermissionTo($permission);
            if ($has) {
                $this->line("✓ {$permission}: SIM");
            } else {
                $this->error("✗ {$permission}: NÃO");
            }
        }
        $this->newLine();
        
        $this->info("=== VERIFICAÇÃO DE SUPER-ADMIN ===");
        $isSuperAdmin = $user->hasRole('super-admin');
        if ($isSuperAdmin) {
            $this->line("✓ Usuário é SUPER-ADMIN (tem acesso total via Gate)");
        } else {
            $this->line("✗ Usuário NÃO é super-admin");
        }
        
        return 0;
    }
}
