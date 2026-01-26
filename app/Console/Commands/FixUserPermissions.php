<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixUserPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:fix-permissions {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Atribui o role manager e suas permissões a um usuário';

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
        
        $this->info("Atribuindo role 'manager' ao usuário {$user->name}...");
        
        // Remove roles anteriores e atribui apenas manager
        $user->syncRoles(['manager']);
        
        $this->info("✓ Role 'manager' atribuído com sucesso!");
        $this->newLine();
        
        // Verifica se o role manager existe e tem as permissões corretas
        $managerRole = \Spatie\Permission\Models\Role::where('name', 'manager')->first();
        
        if (! $managerRole) {
            $this->error("Role 'manager' não encontrado no banco de dados!");
            $this->info("Execute: php artisan db:seed --class=RolesAndPermissionsSeeder");
            return 1;
        }
        
        $this->info("=== PERMISSÕES DO ROLE MANAGER ===");
        $permissions = $managerRole->permissions;
        foreach ($permissions as $permission) {
            $this->line("✓ {$permission->name}");
        }
        $this->newLine();
        
        // Limpa cache de permissões
        $this->info("Limpando cache de permissões...");
        \Illuminate\Support\Facades\Artisan::call('permission:cache-reset');
        $this->info("✓ Cache limpo!");
        
        $this->newLine();
        $this->info("=== VERIFICAÇÃO FINAL ===");
        
        // Recarrega o usuário
        $user->refresh();
        
        $criticalPermissions = ['pos.access', 'pos.discount'];
        foreach ($criticalPermissions as $permission) {
            $has = $user->hasPermissionTo($permission);
            if ($has) {
                $this->line("✓ {$permission}: SIM");
            } else {
                $this->error("✗ {$permission}: NÃO");
            }
        }
        
        return 0;
    }
}
