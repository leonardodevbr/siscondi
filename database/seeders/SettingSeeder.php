<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        // Nome do sistema (sobrescreve APP_NAME quando definido)
        Settings::set('app_name', config('app.name'), 'string', 'general');

        // Dados do município (cabeçalho PDF, relatórios)
        Settings::set('municipality_name', 'Prefeitura Municipal de Cafarnaum', 'string', 'municipality');
        Settings::set('municipality_state', 'Bahia', 'string', 'municipality');
        Settings::set('municipality_address', 'Avenida João Costa Brasil, 315 - Centro - Cafarnaum - BA', 'string', 'municipality');
        Settings::set('municipality_email', 'social@cafarnaum.ba.gov.br', 'string', 'municipality');
        Settings::set('municipality_cnpj', '17.622.151/0001-84', 'string', 'municipality');

        $this->command->info('Configurações iniciais criadas (app_name, dados do município).');
    }
}
