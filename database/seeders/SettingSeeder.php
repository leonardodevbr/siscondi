<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Configurações globais do sistema (apenas o que não está em outras tabelas).
     * Dados do município ficam na tabela municipalities.
     */
    public function run(): void
    {
        Settings::set('app_name', config('app.name'), 'string', 'general');

        $this->command->info('Configurações iniciais criadas (app_name).');
    }
}
