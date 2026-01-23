<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PosController extends Controller
{
    /**
     * Busca venda ativa (status='open') do usuário/caixa atual.
     */
    public function activeSale(Request $request): JsonResponse
    {
        $user = $request->user();
        $cashRegister = CashRegister::where('user_id', $user->id)
            ->where('status', CashRegisterStatus::OPEN)
            ->first();

        if (! $cashRegister) {
            return response()->json([
                'sale' => null,
                'message' => 'No open cash register',
            ], 200);
        }

        $sale = Sale::where('user_id', $user->id)
            ->where('cash_register_id', $cashRegister->id)
            ->where('status', SaleStatus::OPEN)
            ->with([
                'items.productVariant.product',
                'customer',
                'salePayments',
            ])
            ->first();

        if (! $sale) {
            return response()->json([
                'sale' => null,
                'message' => 'No active sale',
            ], 200);
        }

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
        ], 200);
    }

    /**
     * Cria uma nova venda 'open'.
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->input('branch_id') ?? $user->branch_id;

        if (! $branchId) {
            return response()->json([
                'message' => 'branch_id is required',
            ], 422);
        }

        $cashRegister = CashRegister::where('user_id', $user->id)
            ->where('status', CashRegisterStatus::OPEN)
            ->first();

        if (! $cashRegister) {
            return response()->json([
                'message' => 'No open cash register',
            ], 400);
        }

        $existingSale = Sale::where('user_id', $user->id)
            ->where('cash_register_id', $cashRegister->id)
            ->where('status', SaleStatus::OPEN)
            ->first();

        if ($existingSale) {
            return response()->json([
                'message' => 'Já existe uma venda em andamento. Finalize ou cancele a venda atual antes de iniciar uma nova.',
                'sale' => $this->formatSaleResponse($existingSale),
            ], 409);
        }

        $sale = DB::transaction(function () use ($user, $branchId, $cashRegister, $request): Sale {
            $customerId = $request->input('customer_id');

            $sale = Sale::create([
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'cash_register_id' => $cashRegister->id,
                'customer_id' => $customerId,
                'total_amount' => 0,
                'discount_amount' => 0,
                'final_amount' => 0,
                'status' => SaleStatus::OPEN,
            ]);

            return $sale->load(['items.productVariant.product', 'customer', 'salePayments']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
        ], 201);
    }

    /**
     * Adiciona item à venda.
     */
    public function addItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::findOrFail($request->input('sale_id'));

        if ($sale->status !== SaleStatus::OPEN) {
            return response()->json([
                'message' => 'Sale is not open',
            ], 400);
        }

        $sale = DB::transaction(function () use ($sale, $request): Sale {
            $variantId = $request->input('product_variant_id');
            $quantity = (int) $request->input('quantity');

            $variant = ProductVariant::with('product')
                ->lockForUpdate()
                ->findOrFail($variantId);

            $inventory = Inventory::where('branch_id', $sale->branch_id)
                ->where('product_variant_id', $variantId)
                ->first();

            $availableStock = $inventory?->quantity ?? 0;

            if ($availableStock < $quantity) {
                throw new \InvalidArgumentException("Estoque insuficiente. Disponível: {$availableStock}");
            }

            $unitPrice = $variant->getEffectivePrice();
            $totalPrice = $unitPrice * $quantity;

            $existingItem = SaleItem::where('sale_id', $sale->id)
                ->where('product_variant_id', $variantId)
                ->first();

            if ($existingItem) {
                $existingItem->quantity += $quantity;
                $existingItem->total_price = $existingItem->unit_price * $existingItem->quantity;
                $existingItem->save();
            } else {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);
            }

            $this->recalculateSaleTotals($sale);

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
        ], 200);
    }

    /**
     * Remove item da venda.
     */
    public function removeItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
            'item_id' => ['required', 'exists:sale_items,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::findOrFail($request->input('sale_id'));

        if ($sale->status !== SaleStatus::OPEN) {
            return response()->json([
                'message' => 'Sale is not open',
            ], 400);
        }

        $sale = DB::transaction(function () use ($sale, $request): Sale {
            $itemId = $request->input('item_id');
            $item = SaleItem::where('sale_id', $sale->id)
                ->findOrFail($itemId);

            $item->delete();

            $this->recalculateSaleTotals($sale);

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
        ], 200);
    }

    /**
     * Identifica cliente na venda.
     */
    public function identifyCustomer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::findOrFail($request->input('sale_id'));

        if ($sale->status !== SaleStatus::OPEN) {
            return response()->json([
                'message' => 'Sale is not open',
            ], 400);
        }

        $sale->customer_id = $request->input('customer_id');
        $sale->save();

        return response()->json([
            'sale' => $this->formatSaleResponse($sale->fresh(['items.productVariant.product', 'customer', 'salePayments'])),
        ], 200);
    }

    /**
     * Aplica desconto na venda.
     */
    public function applyDiscount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::findOrFail($request->input('sale_id'));

        if ($sale->status !== SaleStatus::OPEN) {
            return response()->json([
                'message' => 'Sale is not open',
            ], 400);
        }

        $sale = DB::transaction(function () use ($sale, $request): Sale {
            $type = $request->input('type');
            $value = (float) $request->input('value');

            if ($type === 'percentage') {
                $discountAmount = $sale->total_amount * ($value / 100);
            } else {
                $discountAmount = min($value, $sale->total_amount);
            }

            $sale->discount_amount = $discountAmount;
            $sale->final_amount = $sale->total_amount - $discountAmount;
            $sale->save();

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
        ], 200);
    }

    /**
     * Adiciona pagamento à venda.
     */
    public function payment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
            'method' => ['required', 'string', 'in:credit_card,debit_card,cash,pix'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::findOrFail($request->input('sale_id'));

        if ($sale->status !== SaleStatus::OPEN) {
            return response()->json([
                'message' => 'Sale is not open',
            ], 400);
        }

        $sale = DB::transaction(function () use ($sale, $request): Sale {
            $method = $request->input('method');
            $amount = (float) $request->input('amount');
            $installments = (int) ($request->input('installments') ?? 1);

            SalePayment::create([
                'sale_id' => $sale->id,
                'method' => $method,
                'amount' => $amount,
                'installments' => $installments,
            ]);

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments']);
        });

        $totalPayments = $sale->salePayments->sum('amount');
        $canFinish = $totalPayments >= $sale->final_amount;

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
            'total_payments' => $totalPayments,
            'can_finish' => $canFinish,
        ], 200);
    }

    /**
     * Finaliza a venda.
     */
    public function finish(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::with(['items.productVariant', 'salePayments'])
            ->findOrFail($request->input('sale_id'));

        if ($sale->status !== SaleStatus::OPEN) {
            return response()->json([
                'message' => 'Sale is not open',
            ], 400);
        }

        $totalPayments = $sale->salePayments->sum('amount');

        if ($totalPayments < $sale->final_amount) {
            return response()->json([
                'message' => 'Payment amount is insufficient',
                'required' => $sale->final_amount,
                'paid' => $totalPayments,
            ], 400);
        }

        $sale = DB::transaction(function () use ($sale, $request): Sale {
            $user = $request->user();

            foreach ($sale->items as $item) {
                $inventory = Inventory::where('branch_id', $sale->branch_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($inventory) {
                    $inventory->decrement('quantity', $item->quantity);

                    StockMovement::create([
                        'branch_id' => $sale->branch_id,
                        'product_variant_id' => $item->product_variant_id,
                        'type' => StockMovementType::SALE,
                        'quantity' => $item->quantity,
                        'reason' => "Venda #{$sale->id}",
                        'user_id' => $user->id,
                    ]);
                }
            }

            $hasPixPayment = $sale->salePayments->contains(fn (SalePayment $payment) => 
                $payment->method === PaymentMethod::PIX
            );

            $sale->status = $hasPixPayment ? SaleStatus::PENDING_PAYMENT : SaleStatus::COMPLETED;
            $sale->save();

            if (! $hasPixPayment && $sale->cashRegister) {
                $cashRegister = $sale->cashRegister;
                $cashRegister->transactions()->create([
                    'type' => \App\Enums\CashRegisterTransactionType::SALE,
                    'amount' => $sale->final_amount,
                    'description' => "Venda #{$sale->id}",
                    'sale_id' => $sale->id,
                ]);
            }

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments', 'user', 'branch']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
            'message' => 'Sale completed successfully',
        ], 200);
    }

    /**
     * Cancela a venda (muda status para 'canceled').
     */
    public function cancel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::findOrFail($request->input('sale_id'));

        if ($sale->status !== SaleStatus::OPEN) {
            return response()->json([
                'message' => 'Sale is not open',
            ], 400);
        }

        $sale = DB::transaction(function () use ($sale): Sale {
            $sale->status = SaleStatus::CANCELED;
            $sale->save();

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
            'message' => 'Sale canceled successfully',
        ], 200);
    }

    /**
     * Recalcula totais da venda.
     */
    private function recalculateSaleTotals(Sale $sale): void
    {
        $sale->refresh();
        $totalAmount = $sale->items()->sum('total_price');
        $discountAmount = $sale->discount_amount;
        $finalAmount = $totalAmount - $discountAmount;

        $sale->total_amount = $totalAmount;
        $sale->final_amount = max(0, $finalAmount);
        $sale->save();
    }

    /**
     * Formata resposta da venda.
     *
     * @return array<string, mixed>
     */
    private function formatSaleResponse(Sale $sale): array
    {
        $totalPayments = $sale->salePayments->sum('amount');

        return [
            'id' => $sale->id,
            'user_id' => $sale->user_id,
            'branch_id' => $sale->branch_id,
            'cash_register_id' => $sale->cash_register_id,
            'customer_id' => $sale->customer_id,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
                'document' => $sale->customer->document,
            ] : null,
            'total_amount' => (float) $sale->total_amount,
            'discount_amount' => (float) $sale->discount_amount,
            'final_amount' => (float) $sale->final_amount,
            'status' => $sale->status->value,
            'coupon_code' => $sale->coupon_code,
            'items' => $sale->items->map(function (SaleItem $item) {
                return [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->productVariant->product->name ?? 'N/A',
                    'variant_attributes' => $item->productVariant->attributes ?? [],
                    'sku' => $item->productVariant->sku ?? null,
                    'barcode' => $item->productVariant->barcode ?? null,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                ];
            }),
            'payments' => $sale->salePayments->map(function (SalePayment $payment) {
                return [
                    'id' => $payment->id,
                    'method' => $payment->method->value,
                    'amount' => (float) $payment->amount,
                    'installments' => $payment->installments,
                ];
            }),
            'total_payments' => $totalPayments,
            'can_finish' => $totalPayments >= $sale->final_amount,
            'created_at' => $sale->created_at,
            'updated_at' => $sale->updated_at,
        ];
    }
}
