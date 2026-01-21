<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::firstOrCreate(
            ['name' => 'Filial Shopping'],
            ['name' => 'Filial Shopping', 'is_main' => false]
        );

        $this->command->info('Branches: Matriz (from migration) + Filial Shopping');
    }
}
