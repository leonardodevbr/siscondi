<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CreateStockEntryAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, User $user): void
    {
        DB::transaction(function () use ($data, $user): void {
            $items = $data['items'];
            $supplierId = $data['supplier_id'] ?? null;
            $reason = $data['reason'] ?? null;

            $productIds = array_column($items, 'product_id');
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                $newCostPrice = $item['cost_price'] ?? null;

                $product = $products->get($productId);

                if (! $product) {
                    throw new \InvalidArgumentException("Product with ID {$productId} not found.");
                }

                StockMovement::create([
                    'product_id' => $productId,
                    'user_id' => $user->id,
                    'type' => StockMovementType::ENTRY,
                    'quantity' => $quantity,
                    'reason' => $reason,
                ]);

                $product->increment('stock_quantity', $quantity);

                if ($newCostPrice !== null) {
                    $product->update(['cost_price' => $newCostPrice]);
                }
            }
        });
    }
}
