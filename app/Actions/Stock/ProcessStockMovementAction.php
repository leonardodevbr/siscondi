<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessStockMovementAction
{
    /**
     * Processa uma movimentação de estoque e atualiza o inventário.
     * 
     * Regras:
     * - ENTRY: Adiciona ao estoque
     * - SALE: Subtrai do estoque
     * - RETURN: Adiciona ao estoque (devolução)
     * - LOSS: Subtrai do estoque (perda)
     * - ADJUSTMENT: Pode adicionar ou subtrair (baseado na quantidade com sinal)
     */
    public function execute(StockMovement $stockMovement): void
    {
        DB::transaction(function () use ($stockMovement): void {
            $branchId = $stockMovement->branch_id;
            $variantId = $stockMovement->product_variant_id;
            $quantity = $stockMovement->quantity;
            $type = $stockMovement->type;

            if (! $branchId) {
                Log::warning('StockMovement criado sem branch_id', [
                    'stock_movement_id' => $stockMovement->id,
                ]);
                return;
            }

            if (! $variantId) {
                Log::warning('StockMovement criado sem product_variant_id', [
                    'stock_movement_id' => $stockMovement->id,
                ]);
                return;
            }

            $quantityChange = $this->calculateQuantityChange($type, $quantity);

            if ($quantityChange === 0) {
                return;
            }

            $inventory = Inventory::firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'product_variant_id' => $variantId,
                ],
                [
                    'quantity' => 0,
                    'min_quantity' => 0,
                ]
            );

            // Usa increment/decrement para evitar race conditions
            if ($quantityChange > 0) {
                $inventory->increment('quantity', $quantityChange);
            } else {
                $inventory->decrement('quantity', abs($quantityChange));
            }

            Log::info('Estoque atualizado automaticamente', [
                'stock_movement_id' => $stockMovement->id,
                'branch_id' => $branchId,
                'product_variant_id' => $variantId,
                'type' => $type->value,
                'quantity_change' => $quantityChange,
                'new_quantity' => $inventory->fresh()->quantity,
            ]);
        });
    }

    /**
     * Calcula a mudança de quantidade baseada no tipo de movimentação.
     * 
     * @return int Positivo = adiciona, Negativo = subtrai
     */
    private function calculateQuantityChange(StockMovementType $type, int $quantity): int
    {
        return match ($type) {
            StockMovementType::ENTRY => $quantity,
            StockMovementType::RETURN => $quantity,
            StockMovementType::SALE => -$quantity,
            StockMovementType::LOSS => -$quantity,
            StockMovementType::ADJUSTMENT => $quantity, // Pode ser positivo ou negativo
        };
    }
}
