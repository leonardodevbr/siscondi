<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Água', 'description' => 'Conta de água'],
            ['name' => 'Energia Elétrica', 'description' => 'Conta de luz'],
            ['name' => 'Internet', 'description' => 'Conta de internet'],
            ['name' => 'Telefone', 'description' => 'Conta de telefone'],
            ['name' => 'Aluguel', 'description' => 'Pagamento de aluguel'],
            ['name' => 'Salários', 'description' => 'Pagamento de salários'],
            ['name' => 'Impostos', 'description' => 'Pagamento de impostos'],
            ['name' => 'Manutenção', 'description' => 'Manutenção de equipamentos e infraestrutura'],
            ['name' => 'Marketing', 'description' => 'Despesas com marketing e publicidade'],
            ['name' => 'Material de Escritório', 'description' => 'Materiais de uso geral'],
            ['name' => 'Limpeza', 'description' => 'Produtos e serviços de limpeza'],
            ['name' => 'Segurança', 'description' => 'Serviços de segurança'],
            ['name' => 'Outras', 'description' => 'Outras despesas diversas'],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::query()->firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
