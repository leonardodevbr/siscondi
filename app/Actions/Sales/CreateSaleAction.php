<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection as SupportCollection;

class CreateSaleAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, User $user): Sale
    {
        return DB::transaction(function () use ($data, $user): Sale {
            $items = $data['items'];
            $payments = $data['payments'];
            $customerId = $data['customer_id'] ?? null;
            $discountAmount = $data['discount_amount'] ?? 0;
            $note = $data['note'] ?? null;

            $productIds = array_column($items, 'product_id');
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->validateStock($items, $products);

            $totalAmount = $this->calculateTotalAmount($items, $products);
            $finalAmount = $totalAmount - $discountAmount;

            $sale = Sale::create([
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'status' => SaleStatus::COMPLETED,
                'note' => $note,
            ]);

            $this->createSaleItems($sale, $items, $products);
            $this->createPayments($sale, $payments);
            $this->decrementStock($sale, $items, $products, $user);

            $sale->load(['items.product', 'payments', 'customer', 'user']);

            return $sale;
        });
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param Collection<int, Product> $products
     */
    private function validateStock(array $items, Collection $products): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            $product = $products->get($productId);

            if (! $product) {
                throw new \InvalidArgumentException("Product with ID {$productId} not found.");
            }

            if ($product->stock_quantity < $quantity) {
                throw new \InvalidArgumentException(
                    "Insufficient stock for product {$product->name}. Available: {$product->stock_quantity}, Requested: {$quantity}"
                );
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param Collection<int, Product> $products
     */
    private function calculateTotalAmount(array $items, Collection $products): float
    {
        $total = 0;

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            $product = $products->get($productId);
            $unitPrice = $product->sell_price;
            $total += $unitPrice * $quantity;
        }

        return $total;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param Collection<int, Product> $products
     */
    private function createSaleItems(Sale $sale, array $items, Collection $products): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            $product = $products->get($productId);
            $unitPrice = $product->sell_price;
            $totalPrice = $unitPrice * $quantity;

            $sale->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     */
    private function createPayments(Sale $sale, array $payments): void
    {
        foreach ($payments as $payment) {
            $sale->payments()->create([
                'method' => $payment['method'],
                'amount' => $payment['amount'],
                'installments' => $payment['installments'] ?? 1,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param Collection<int, Product> $products
     */
    private function decrementStock(Sale $sale, array $items, Collection $products, User $user): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            $product = $products->get($productId);
            $product->decrement('stock_quantity', $quantity);

            StockMovement::create([
                'product_id' => $productId,
                'user_id' => $user->id,
                'type' => StockMovementType::SALE,
                'quantity' => $quantity,
                'reason' => "Sale #{$sale->id}",
            ]);
        }
    }
}
