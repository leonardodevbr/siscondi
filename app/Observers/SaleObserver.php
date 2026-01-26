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
     * 
     * NOTA: Este Observer escuta APENAS o evento 'updated'.
     * Não confundir com 'saved' (que dispara em create E update).
     */
    public function updated(Sale $sale): void
    {
        // Proteção 1: Só processa se o status realmente mudou
        if (! $sale->wasChanged('status')) {
            return;
        }

        // Proteção 2: Verifica se está "sujo" (dirty) - garante que mudou nesta requisição
        if (! $sale->isDirty('status') && ! $sale->wasChanged('status')) {
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
        // Validação crítica: branch_id obrigatório
        if (! $sale->branch_id) {
            Log::error('SaleObserver: Venda sem branch_id - impossível processar estoque', [
                'sale_id' => $sale->id,
                'user_id' => $sale->user_id,
            ]);
            return;
        }

        // Validação crítica: user_id obrigatório
        if (! $sale->user_id) {
            Log::error('SaleObserver: Venda sem user_id - impossível processar estoque', [
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
            ]);
            return;
        }

        // Previne duplicação: verifica se já existem movimentações para esta venda
        $reason = "Venda #{$sale->id}";
        $movementsAlreadyExist = StockMovement::where('reason', $reason)
            ->where('type', StockMovementType::SALE)
            ->exists();

        if ($movementsAlreadyExist) {
            return;
        }

        try {
            DB::transaction(function () use ($sale, $reason): void {
                $sale->load('items.productVariant');

                if ($sale->items->isEmpty()) {
                    Log::warning('SaleObserver: Venda finalizada sem itens', [
                        'sale_id' => $sale->id,
                    ]);
                    return;
                }

                $totalItemsProcessed = 0;
                $itemsWithoutVariant = 0;

                foreach ($sale->items as $item) {
                    if (! $item->product_variant_id) {
                        Log::warning('SaleObserver: SaleItem sem product_variant_id', [
                            'sale_id' => $sale->id,
                            'sale_item_id' => $item->id,
                        ]);
                        $itemsWithoutVariant++;
                        continue;
                    }

                    try {
                        // Cria StockMovement (tabela única e moderna para todas as movimentações)
                        $stockMovement = StockMovement::create([
                            'product_variant_id' => $item->product_variant_id,
                            'branch_id' => $sale->branch_id,
                            'user_id' => $sale->user_id,
                            'type' => StockMovementType::SALE,
                            'quantity' => $item->quantity,
                            'reason' => $reason,
                        ]);

                        $totalItemsProcessed++;
                    } catch (\Throwable $e) {
                        Log::error('SaleObserver: ERRO ao criar StockMovement', [
                            'sale_id' => $sale->id,
                            'sale_item_id' => $item->id,
                            'product_variant_id' => $item->product_variant_id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e; // Re-lança para fazer rollback da transaction
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('SaleObserver: ERRO CRÍTICO ao processar baixa de estoque', [
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'user_id' => $sale->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // NÃO re-lança - permite que a venda seja finalizada mesmo se o estoque falhar
            // O erro já foi logado e pode ser corrigido manualmente depois
        }
    }

    /**
     * Devolve o estoque quando uma venda COMPLETED é cancelada.
     * 
     * Para cada item da venda, cria um StockMovement tipo RETURN.
     * O StockMovementObserver irá adicionar automaticamente ao inventário.
     */
    private function returnStockOnCancellation(Sale $sale): void
    {
        // Validação crítica: branch_id obrigatório
        if (! $sale->branch_id) {
            Log::error('SaleObserver: Venda sem branch_id - impossível devolver estoque', [
                'sale_id' => $sale->id,
                'user_id' => $sale->user_id,
            ]);
            return;
        }

        // Validação crítica: user_id obrigatório
        if (! $sale->user_id) {
            Log::error('SaleObserver: Venda sem user_id - impossível devolver estoque', [
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
            ]);
            return;
        }

        $reason = "Cancelamento Venda #{$sale->id}";
        
        // Previne duplicação: verifica se já existem devoluções para esta venda
        $returnsAlreadyExist = StockMovement::where('reason', $reason)
            ->where('type', StockMovementType::RETURN)
            ->exists();

        if ($returnsAlreadyExist) {
            return;
        }

        try {
            DB::transaction(function () use ($sale, $reason): void {
                $sale->load('items.productVariant');

                if ($sale->items->isEmpty()) {
                    Log::warning('SaleObserver: Venda cancelada sem itens', [
                        'sale_id' => $sale->id,
                    ]);
                    return;
                }

                $totalItemsProcessed = 0;

                foreach ($sale->items as $item) {
                    if (! $item->product_variant_id) {
                        Log::warning('SaleObserver: SaleItem sem product_variant_id (cancelamento)', [
                            'sale_id' => $sale->id,
                            'sale_item_id' => $item->id,
                        ]);
                        continue;
                    }

                    try {
                        // Cria StockMovement RETURN (tabela única e moderna)
                        $stockMovement = StockMovement::create([
                            'product_variant_id' => $item->product_variant_id,
                            'branch_id' => $sale->branch_id,
                            'user_id' => $sale->user_id,
                            'type' => StockMovementType::RETURN,
                            'quantity' => $item->quantity,
                            'reason' => $reason,
                        ]);

                        $totalItemsProcessed++;
                    } catch (\Throwable $e) {
                        Log::error('SaleObserver: ERRO ao criar StockMovement RETURN', [
                            'sale_id' => $sale->id,
                            'sale_item_id' => $item->id,
                            'product_variant_id' => $item->product_variant_id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e; // Re-lança para fazer rollback da transaction
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('SaleObserver: ERRO CRÍTICO ao processar devolução de estoque', [
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'user_id' => $sale->user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // NÃO re-lança - permite que o cancelamento seja processado mesmo se o estoque falhar
            // O erro já foi logado e pode ser corrigido manualmente depois
        }
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
