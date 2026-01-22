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
            $user = auth()->user();
            $branchId = $user?->branch_id ?? request()->header('X-Branch-ID');

            if (! $branchId) {
                Log::warning('InventoryMovement created without branch_id', [
                    'movement_id' => $inventoryMovement->id,
                    'user_id' => $user?->id,
                ]);
                return;
            }

            $branchId = (int) $branchId;

            $quantityChange = match ($inventoryMovement->operation) {
                'add' => $inventoryMovement->quantity,
                'sub' => -$inventoryMovement->quantity,
                default => 0,
            };

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
