<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Stock\ProcessStockMovementAction;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;

class StockMovementObserver
{
    public function __construct(
        private readonly ProcessStockMovementAction $processStockMovementAction
    ) {
    }

    /**
     * Dispara quando um StockMovement é criado.
     * Atualiza automaticamente o inventário (tabela inventories).
     */
    public function created(StockMovement $stockMovement): void
    {
        try {
            $this->processStockMovementAction->execute($stockMovement);
        } catch (\Throwable $e) {
            Log::error('Erro ao processar movimentação de estoque', [
                'stock_movement_id' => $stockMovement->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
