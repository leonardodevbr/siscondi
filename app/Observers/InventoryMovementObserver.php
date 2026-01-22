<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryMovementObserver
{
    /**
     * Handle the InventoryMovement "created" event.
     */
    public function created(InventoryMovement $inventoryMovement): void
    {
        DB::transaction(function () use ($inventoryMovement): void {
            $branchId = $inventoryMovement->branch_id;

            if (! $branchId) {
                Log::warning('InventoryMovement created without branch_id', [
                    'movement_id' => $inventoryMovement->id,
                ]);
                return;
            }

            $branchId = (int) $branchId;

            $quantityChange = 0;
            if ($inventoryMovement->operation === 'add') {
                $quantityChange = $inventoryMovement->quantity;
            } elseif ($inventoryMovement->operation === 'sub') {
                $quantityChange = -$inventoryMovement->quantity;
            }

            if ($quantityChange === 0) {
                return;
            }

            if ($inventoryMovement->variation_id) {
                $inventory = Inventory::firstOrCreate(
                    [
                        'branch_id' => $branchId,
                        'product_variant_id' => $inventoryMovement->variation_id,
                    ],
                    [
                        'quantity' => 0,
                        'min_quantity' => 0,
                    ]
                );

                $inventory->increment('quantity', $quantityChange);
            } else {
                $product = $inventoryMovement->product;
                if ($product && ! $product->has_variants) {
                    $variant = $product->variants()->first();
                    if ($variant) {
                        $inventory = Inventory::firstOrCreate(
                            [
                                'branch_id' => $branchId,
                                'product_variant_id' => $variant->id,
                            ],
                            [
                                'quantity' => 0,
                                'min_quantity' => 0,
                            ]
                        );

                        $inventory->increment('quantity', $quantityChange);
                    }
                }
            }
        });
    }
}
