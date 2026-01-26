<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IMPORTANTE: Este Observer NÃO deve fazer verificações de autorização.
 * Ele é disparado automaticamente pelo Eloquent e roda no contexto do sistema.
 */

class SaleObserver
{
    /**
     * Dispara quando uma Sale é atualizada.
     * 
     * Verifica se o status mudou para COMPLETED ou CANCELED e processa a movimentação de estoque.
     */
    public function updated(Sale $sale): void
    {
        // Verifica se o status mudou
        if (! $sale->wasChanged('status')) {
            return;
        }

        $oldStatus = $sale->getOriginal('status');
        $newStatus = $sale->status;

        // Se mudou para COMPLETED, baixa o estoque
        if ($newStatus === SaleStatus::COMPLETED) {
            $this->processStockOutOnCompletion($sale);
        }

        // Se estava COMPLETED e mudou para CANCELED, devolve o estoque
        if ($oldStatus === SaleStatus::COMPLETED->value && $newStatus === SaleStatus::CANCELED) {
            $this->returnStockOnCancellation($sale);
        }
    }

    /**
     * Processa a baixa de estoque quando a venda é finalizada (COMPLETED).
     */
    private function processStockOutOnCompletion(Sale $sale): void
    {
        // Verifica se já existem movimentações para esta venda (evita duplicação)
        $reason = "Venda #{$sale->id}";
        $movementsAlreadyExist = StockMovement::where('reason', $reason)->exists();

        if ($movementsAlreadyExist) {
            Log::info('StockMovements já existem para esta venda', [
                'sale_id' => $sale->id,
                'reason' => $reason,
            ]);
            return;
        }

        // Cria os StockMovements - o StockMovementObserver irá atualizar o inventário automaticamente
        DB::transaction(function () use ($sale, $reason): void {
            $sale->load('items');

            foreach ($sale->items as $item) {
                if (! $item->product_variant_id) {
                    Log::warning('SaleItem sem product_variant_id', [
                        'sale_id' => $sale->id,
                        'sale_item_id' => $item->id,
                    ]);
                    continue;
                }

                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'branch_id' => $sale->branch_id,
                    'user_id' => $sale->user_id,
                    'type' => StockMovementType::SALE,
                    'quantity' => $item->quantity,
                    'reason' => $reason,
                ]);

                Log::info('StockMovement criado via SaleObserver', [
                    'sale_id' => $sale->id,
                    'product_variant_id' => $item->product_variant_id,
                    'branch_id' => $sale->branch_id,
                    'quantity' => $item->quantity,
                ]);
            }
        });
    }

    /**
     * Devolve o estoque quando uma venda COMPLETED é cancelada.
     */
    private function returnStockOnCancellation(Sale $sale): void
    {
        $reason = "Cancelamento Venda #{$sale->id}";
        
        DB::transaction(function () use ($sale, $reason): void {
            $sale->load('items');

            foreach ($sale->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }

                // Cria StockMovement tipo RETURN para devolver o estoque
                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'branch_id' => $sale->branch_id,
                    'user_id' => $sale->user_id,
                    'type' => StockMovementType::RETURN,
                    'quantity' => $item->quantity,
                    'reason' => $reason,
                ]);

                Log::info('Estoque devolvido por cancelamento', [
                    'sale_id' => $sale->id,
                    'product_variant_id' => $item->product_variant_id,
                    'branch_id' => $sale->branch_id,
                    'quantity' => $item->quantity,
                ]);
            }
        });
    }
}
