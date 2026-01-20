<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\Inventory;
use App\Models\ProductVariant;
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
            $branchId = $data['branch_id'] ?? null;
            $reason = $data['reason'] ?? null;

            if (! $branchId) {
                throw new \InvalidArgumentException('branch_id is required.');
            }

            $variantIds = array_column($items, 'product_variant_id');
            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $variantId = $item['product_variant_id'];
                $quantity = $item['quantity'];
                $newCostPrice = $item['cost_price'] ?? null;

                $variant = $variants->get($variantId);

                if (! $variant) {
                    throw new \InvalidArgumentException("Product variant with ID {$variantId} not found.");
                }

                StockMovement::create([
                    'product_variant_id' => $variantId,
                    'branch_id' => $branchId,
                    'user_id' => $user->id,
                    'type' => StockMovementType::ENTRY,
                    'quantity' => $quantity,
                    'reason' => $reason,
                ]);

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

                $inventory->increment('quantity', $quantity);

                if ($newCostPrice !== null && $variant->product) {
                    $variant->product->update(['cost_price' => $newCostPrice]);
                }
            }
        });
    }
}
