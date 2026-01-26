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
 * Observer responsável por gerenciar a baixa/devolução de estoque automaticamente
 * quando uma venda muda de status.
 * 
 * IMPORTANTE: Este Observer NÃO deve fazer verificações de autorização.
 * Ele é disparado automaticamente pelo Eloquent e roda no contexto do sistema.
 * 
 * Fluxo:
 * 1. SaleObserver detecta mudança de status
 * 2. Cria StockMovement(s) apropriado(s)
 * 3. StockMovementObserver intercepta a criação
 * 4. ProcessStockMovementAction atualiza o Inventory
 */
class SaleObserver
{
    /**
     * Dispara quando uma Sale é atualizada.
     * 
     * Verifica se o status mudou e processa a movimentação de estoque adequada.
     */
    public function updated(Sale $sale): void
    {
        // Verifica se o status mudou
        if (! $sale->wasChanged('status')) {
            return;
        }

        $oldStatus = $this->getOriginalStatusAsEnum($sale);
        $newStatus = $sale->status;

        // Regra 1: Se mudou para COMPLETED, baixa o estoque
        if ($newStatus === SaleStatus::COMPLETED) {
            $this->processStockOutOnCompletion($sale);
            return;
        }

        // Regra 2: Se estava COMPLETED e mudou para CANCELED, devolve o estoque
        if ($oldStatus === SaleStatus::COMPLETED && $newStatus === SaleStatus::CANCELED) {
            $this->returnStockOnCancellation($sale);
            return;
        }
    }

    /**
     * Processa a baixa de estoque quando a venda é finalizada (COMPLETED).
     * 
     * Para cada item da venda, cria um StockMovement tipo SALE.
     * O StockMovementObserver irá atualizar automaticamente a tabela inventories.
     */
    private function processStockOutOnCompletion(Sale $sale): void
    {
        if (! $sale->branch_id) {
            Log::error('Venda sem branch_id ao tentar baixar estoque', [
                'sale_id' => $sale->id,
            ]);
            return;
        }

        // Previne duplicação: verifica se já existem movimentações para esta venda
        $reason = "Venda #{$sale->id}";
        $movementsAlreadyExist = StockMovement::where('reason', $reason)
            ->where('type', StockMovementType::SALE)
            ->exists();

        if ($movementsAlreadyExist) {
            Log::info('StockMovements tipo SALE já existem para esta venda (evitando duplicação)', [
                'sale_id' => $sale->id,
                'reason' => $reason,
            ]);
            return;
        }

        DB::transaction(function () use ($sale, $reason): void {
            $sale->load('items.productVariant');

            if ($sale->items->isEmpty()) {
                Log::warning('Venda finalizada sem itens', [
                    'sale_id' => $sale->id,
                ]);
                return;
            }

            $totalItemsProcessed = 0;
            $itemsWithoutVariant = 0;

            foreach ($sale->items as $item) {
                if (! $item->product_variant_id) {
                    Log::warning('SaleItem sem product_variant_id - estoque não será baixado', [
                        'sale_id' => $sale->id,
                        'sale_item_id' => $item->id,
                    ]);
                    $itemsWithoutVariant++;
                    continue;
                }

                // Cria StockMovement - o Observer irá atualizar o inventário automaticamente
                $stockMovement = StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'branch_id' => $sale->branch_id,
                    'user_id' => $sale->user_id,
                    'type' => StockMovementType::SALE,
                    'quantity' => $item->quantity,
                    'reason' => $reason,
                ]);

                $totalItemsProcessed++;

                Log::info('StockMovement SALE criado via SaleObserver', [
                    'stock_movement_id' => $stockMovement->id,
                    'sale_id' => $sale->id,
                    'product_variant_id' => $item->product_variant_id,
                    'branch_id' => $sale->branch_id,
                    'quantity' => $item->quantity,
                ]);
            }

            Log::info('Processamento de baixa de estoque concluído', [
                'sale_id' => $sale->id,
                'total_items_processed' => $totalItemsProcessed,
                'items_without_variant' => $itemsWithoutVariant,
            ]);
        });
    }

    /**
     * Devolve o estoque quando uma venda COMPLETED é cancelada.
     * 
     * Para cada item da venda, cria um StockMovement tipo RETURN.
     * O StockMovementObserver irá adicionar automaticamente ao inventário.
     */
    private function returnStockOnCancellation(Sale $sale): void
    {
        if (! $sale->branch_id) {
            Log::error('Venda sem branch_id ao tentar devolver estoque', [
                'sale_id' => $sale->id,
            ]);
            return;
        }

        $reason = "Cancelamento Venda #{$sale->id}";
        
        // Previne duplicação: verifica se já existem devoluções para esta venda
        $returnsAlreadyExist = StockMovement::where('reason', $reason)
            ->where('type', StockMovementType::RETURN)
            ->exists();

        if ($returnsAlreadyExist) {
            Log::info('StockMovements tipo RETURN já existem para esta venda (evitando duplicação)', [
                'sale_id' => $sale->id,
                'reason' => $reason,
            ]);
            return;
        }

        DB::transaction(function () use ($sale, $reason): void {
            $sale->load('items.productVariant');

            if ($sale->items->isEmpty()) {
                Log::warning('Venda cancelada sem itens', [
                    'sale_id' => $sale->id,
                ]);
                return;
            }

            $totalItemsProcessed = 0;

            foreach ($sale->items as $item) {
                if (! $item->product_variant_id) {
                    Log::warning('SaleItem sem product_variant_id - estoque não será devolvido', [
                        'sale_id' => $sale->id,
                        'sale_item_id' => $item->id,
                    ]);
                    continue;
                }

                // Cria StockMovement tipo RETURN - o Observer irá adicionar ao inventário automaticamente
                $stockMovement = StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'branch_id' => $sale->branch_id,
                    'user_id' => $sale->user_id,
                    'type' => StockMovementType::RETURN,
                    'quantity' => $item->quantity,
                    'reason' => $reason,
                ]);

                $totalItemsProcessed++;

                Log::info('StockMovement RETURN criado via SaleObserver', [
                    'stock_movement_id' => $stockMovement->id,
                    'sale_id' => $sale->id,
                    'product_variant_id' => $item->product_variant_id,
                    'branch_id' => $sale->branch_id,
                    'quantity' => $item->quantity,
                ]);
            }

            Log::info('Processamento de devolução de estoque concluído', [
                'sale_id' => $sale->id,
                'total_items_processed' => $totalItemsProcessed,
            ]);
        });
    }

    /**
     * Obtém o status original como Enum para comparações type-safe.
     */
    private function getOriginalStatusAsEnum(Sale $sale): ?SaleStatus
    {
        $originalValue = $sale->getOriginal('status');
        
        if ($originalValue === null) {
            return null;
        }

        // Se já é um Enum, retorna direto
        if ($originalValue instanceof SaleStatus) {
            return $originalValue;
        }

        // Se é string, converte para Enum
        return SaleStatus::from($originalValue);
    }
}
