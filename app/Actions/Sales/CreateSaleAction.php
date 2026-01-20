<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\NoOpenCashRegisterException;
use App\Models\CashRegister;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CreateSaleAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, User $user): Sale
    {
        $cashRegister = CashRegister::where('user_id', $user->id)
            ->where('status', CashRegisterStatus::OPEN)
            ->first();

        if (! $cashRegister) {
            throw new NoOpenCashRegisterException();
        }

        return DB::transaction(function () use ($data, $user, $cashRegister): Sale {
            $items = $data['items'];
            $payments = $data['payments'];
            $customerId = $data['customer_id'] ?? null;
            $note = $data['note'] ?? null;

            $productIds = array_column($items, 'product_id');
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->validateStock($items, $products);

            $totalAmount = $this->calculateTotalAmount($items, $products);
            $discountAmount = $this->calculateDiscount($totalAmount, $data);

            $coupon = $this->validateAndApplyCoupon($data, $totalAmount);
            $couponId = null;

            if ($coupon) {
                $discountAmount = $coupon->calculateDiscount($totalAmount);
                $coupon->incrementUsage();
                $couponId = $coupon->id;
            }

            $finalAmount = $totalAmount - $discountAmount;

            $hasPixPayment = collect($payments)->contains(fn (array $payment) => 
                PaymentMethod::from($payment['method']) === PaymentMethod::PIX
            );

            $saleStatus = $hasPixPayment ? SaleStatus::PENDING_PAYMENT : SaleStatus::COMPLETED;

            $sale = Sale::create([
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'coupon_id' => $couponId,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'status' => $saleStatus,
                'note' => $note,
            ]);

            $this->createSaleItems($sale, $items, $products);
            $this->createPayments($sale, $payments, $hasPixPayment);
            $this->decrementStock($sale, $items, $products, $user);
            
            if (! $hasPixPayment) {
                $this->createCashRegisterTransaction($cashRegister, $sale, $payments);
            }

            $sale->load(['items.product', 'payments', 'customer', 'user', 'coupon']);

            return $sale;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    private function calculateDiscount(float $totalAmount, array $data): float
    {
        $discountType = $data['discount_type'] ?? null;
        $discountValue = (float) ($data['discount_value'] ?? 0);
        $discountAmount = (float) ($data['discount_amount'] ?? 0);

        if ($discountType === 'percentage') {
            return $totalAmount * ($discountValue / 100);
        }

        if ($discountType === 'fixed') {
            return $discountValue;
        }

        return $discountAmount;
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
            $unitPrice = $product->getEffectivePrice();
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
            $unitPrice = $product->getEffectivePrice();
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
    private function createPayments(Sale $sale, array $payments, bool $hasPixPayment): void
    {
        foreach ($payments as $payment) {
            $paymentMethod = PaymentMethod::from($payment['method']);
            $isPix = $paymentMethod === PaymentMethod::PIX;
            $isCard = in_array($paymentMethod, [PaymentMethod::CREDIT_CARD, PaymentMethod::DEBIT_CARD], true);

            $sale->payments()->create([
                'method' => $payment['method'],
                'amount' => $payment['amount'],
                'installments' => $payment['installments'] ?? 1,
                'status' => $isPix ? PaymentStatus::PENDING : PaymentStatus::PAID,
                'card_authorization_code' => $isCard ? ($payment['card_authorization_code'] ?? null) : null,
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

    /**
     * @param array<int, array<string, mixed>> $payments
     */
    private function createCashRegisterTransaction(CashRegister $cashRegister, Sale $sale, array $payments): void
    {
        $cashAmount = 0;

        foreach ($payments as $payment) {
            if ($payment['method'] === PaymentMethod::MONEY->value) {
                $cashAmount += (float) $payment['amount'];
            }
        }

        if ($cashAmount > 0) {
            $cashRegister->transactions()->create([
                'type' => CashRegisterTransactionType::SALE,
                'amount' => $cashAmount,
                'description' => "Venda #{$sale->id}",
                'sale_id' => $sale->id,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @throws InvalidCouponException
     */
    private function validateAndApplyCoupon(array $data, float $totalAmount): ?Coupon
    {
        $couponCode = $data['coupon_code'] ?? null;

        if (! $couponCode) {
            return null;
        }

        $coupon = Coupon::where('code', strtoupper($couponCode))->first();

        if (! $coupon) {
            throw InvalidCouponException::notFound($couponCode);
        }

        if (! $coupon->active) {
            throw InvalidCouponException::inactive($couponCode);
        }

        if ($coupon->starts_at && now()->isBefore($coupon->starts_at)) {
            throw InvalidCouponException::notYetActive($couponCode);
        }

        if ($coupon->expires_at && now()->isAfter($coupon->expires_at)) {
            throw InvalidCouponException::expired($couponCode);
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw InvalidCouponException::usageLimitReached($couponCode);
        }

        if ($coupon->min_purchase_amount !== null && $totalAmount < (float) $coupon->min_purchase_amount) {
            throw InvalidCouponException::minimumPurchaseNotMet($couponCode, (float) $coupon->min_purchase_amount);
        }

        return $coupon;
    }
}
