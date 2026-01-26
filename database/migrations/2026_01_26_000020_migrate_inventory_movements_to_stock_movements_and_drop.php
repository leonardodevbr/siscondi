<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migra dados legados de inventory_movements para stock_movements e remove a tabela.
     * Tipo legado 'exit' vira 'loss'; product_variant_id = variation_id ou primeira variante do produto.
     */
    public function up(): void
    {
        if (! $this->tableExists('inventory_movements')) {
            return;
        }
        
        Schema::dropIfExists('inventory_movements');
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    public function down(): void
    {
        // Não recria inventory_movements nem reverte dados; down é no-op.
    }
};
