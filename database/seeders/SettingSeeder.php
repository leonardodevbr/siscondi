<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * SISCONDI - Configurações do Sistema
         */
        
        // Informações da Prefeitura/Fundo Municipal
        Settings::set('entity_name', 'Prefeitura Municipal de Exemplo', 'string', 'general');
        Settings::set('fund_name', 'Fundo Municipal de Saúde', 'string', 'general');
        Settings::set('cnpj', '00.000.000/0001-00', 'string', 'general');
        Settings::set('address', 'Rua Principal, 100 - Centro', 'string', 'general');
        Settings::set('city', 'Cidade Exemplo', 'string', 'general');
        Settings::set('state', 'MG', 'string', 'general');
        Settings::set('cep', '00000-000', 'string', 'general');
        Settings::set('phone', '(00) 0000-0000', 'string', 'general');
        
        // Responsável/Gestor (para cabeçalho de relatórios)
        Settings::set('manager_name', 'João da Silva', 'string', 'general');
        Settings::set('manager_title', 'Prefeito Municipal', 'string', 'general');
        
        // Logo (path para o arquivo)
        Settings::set('logo_path', '', 'string', 'general');
    }
}

