<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        /**
         * SISCONDI - Sistema de Concessão de Diárias
         * Seeders de Inicialização
         */
        
        $this->call([
            RolesAndPermissionsSeeder::class,
            BranchSeeder::class, // Secretarias
            UserSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
