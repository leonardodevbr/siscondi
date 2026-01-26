<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CashRegisterStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\CashRegister;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\ManagerAuthorizationLog;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PosController extends Controller
{
    /**
     * Busca a venda aberta do usuário atual.
     */
    private function getActiveSale(User $user): ?Sale
    {
        $cashRegister = CashRegister::where('user_id', $user->id)
            ->where('status', CashRegisterStatus::OPEN)
            ->first();

        if (! $cashRegister) {
            return null;
        }

        return Sale::where('user_id', $user->id)
            ->where('cash_register_id', $cashRegister->id)
            ->where('status', SaleStatus::OPEN)
            ->with([
                'items.productVariant.product',
                'customer',
                'salePayments',
                'coupon.products',
                'coupon.categories',
            ])
            ->first();
    }

    /**
     * Busca venda ativa (status='open') do usuário/caixa atual.
     */
    public function activeSale(Request $request): JsonResponse
    {
        $user = $request->user();
        $sale = $this->getActiveSale($user);

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
            'barcode' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $sale = $this->getActiveSale($user);

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento. Inicie uma venda primeiro.',
            ], 400);
        }

        $sale = DB::transaction(function () use ($sale, $request, $user): Sale {
            $barcode = $request->input('barcode');
            $quantity = (int) $request->input('quantity');

            $variant = ProductVariant::where('sku', $barcode)
                ->orWhere('barcode', $barcode)
                ->with('product')
                ->lockForUpdate()
                ->first();

            if (! $variant) {
                throw new \InvalidArgumentException('Produto não encontrado.');
            }

            $variantId = $variant->id;

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
                ->lockForUpdate()
                ->first();

            if ($existingItem) {
                $newQuantity = $existingItem->quantity + $quantity;
                
                if ($availableStock < $newQuantity) {
                    throw new \InvalidArgumentException("Estoque insuficiente. Disponível: {$availableStock}, Solicitado: {$newQuantity}");
                }
                
                $existingItem->quantity = $newQuantity;
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
     * Quando autorizado por gerente, enviar authorized_by_user_id para auditoria.
     */
    public function removeItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
            'item_id' => ['required', 'exists:sale_items,id'],
            'authorized_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
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

        $itemId = (int) $request->input('item_id');
        $authorizedByUserId = $request->has('authorized_by_user_id') ? (int) $request->input('authorized_by_user_id') : null;

        $sale = DB::transaction(function () use ($sale, $itemId, $authorizedByUserId): Sale {
            $item = SaleItem::where('sale_id', $sale->id)
                ->findOrFail($itemId);

            $item->delete();

            if ($authorizedByUserId) {
                ManagerAuthorizationLog::query()->create([
                    'authorized_by_user_id' => $authorizedByUserId,
                    'action' => ManagerAuthorizationLog::ACTION_CANCEL_ITEM,
                    'sale_id' => $sale->id,
                    'branch_id' => $sale->branch_id,
                    'metadata' => ['item_id' => $itemId],
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
     * Remove todas as unidades do item da venda por código de barras/SKU ou por item_id (pesquisa por nome).
     */
    public function removeItemByCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
            'barcode' => ['required_without:item_id', 'nullable', 'string'],
            'item_id' => ['required_without:barcode', 'nullable', 'integer', 'exists:sale_items,id'],
            'authorized_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $saleId = (int) $request->input('sale_id');
        $barcode = $request->input('barcode');
        $itemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $authorizedByUserId = $request->has('authorized_by_user_id') ? (int) $request->input('authorized_by_user_id') : null;

        $sale = Sale::where('id', $saleId)
            ->where('user_id', $user->id)
            ->where('status', SaleStatus::OPEN)
            ->first();

        if (! $sale) {
            return response()->json([
                'message' => 'Venda não encontrada ou não pertence ao usuário atual.',
            ], 404);
        }

        try {
            $sale = DB::transaction(function () use ($sale, $barcode, $itemId, $authorizedByUserId): Sale {
                $item = $itemId
                    ? SaleItem::where('sale_id', $sale->id)->where('id', $itemId)->lockForUpdate()->first()
                    : SaleItem::where('sale_id', $sale->id)
                        ->whereHas('productVariant', function ($q) use ($barcode) {
                            $q->where('sku', $barcode)->orWhere('barcode', $barcode);
                        })
                        ->with('productVariant')
                        ->lockForUpdate()
                        ->first();

                if (! $item) {
                    throw new \InvalidArgumentException('Este produto não consta na venda atual.');
                }

                $itemIdToLog = $item->id;
                $metadata = ['item_id' => $itemIdToLog];
                if ($barcode !== null && $barcode !== '') {
                    $metadata['barcode'] = $barcode;
                }

                $item->delete();

                if ($authorizedByUserId) {
                    ManagerAuthorizationLog::query()->create([
                        'authorized_by_user_id' => $authorizedByUserId,
                        'action' => ManagerAuthorizationLog::ACTION_CANCEL_ITEM,
                        'sale_id' => $sale->id,
                        'branch_id' => $sale->branch_id,
                        'metadata' => $metadata,
                    ]);
                }

                $this->recalculateSaleTotals($sale);

                return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments']);
            });

            return response()->json([
                'sale' => $this->formatSaleResponse($sale),
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Identifica cliente na venda por CPF/CNPJ ou ID.
     */
    public function identifyCustomer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'sale_id' => ['required', 'exists:sales,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $saleId = (int) $request->input('sale_id');

        $sale = Sale::where('id', $saleId)
            ->where('user_id', $user->id)
            ->where('status', SaleStatus::OPEN)
            ->with(['items.productVariant.product', 'customer', 'salePayments'])
            ->first();

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento.',
            ], 400);
        }

        $document = $request->input('document');
        $customerId = $request->input('customer_id');

        if ($document) {
            $cleanDoc = preg_replace('/[^0-9]/', '', $document);

            $formattedDoc = $cleanDoc;
            if (strlen($cleanDoc) === 11) {
                $formattedDoc = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cleanDoc);
            } elseif (strlen($cleanDoc) === 14) {
                $formattedDoc = preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cleanDoc);
            }

            $customer = Customer::query()
                ->where('cpf_cnpj', $cleanDoc)
                ->orWhere('cpf_cnpj', $formattedDoc)
                ->first();

            if (! $customer) {
                return response()->json([
                    'message' => 'Cliente não encontrado',
                    'document_searched' => $cleanDoc,
                ], 404);
            }

            $customerId = $customer->id;
        }

        $sale = DB::transaction(function () use ($sale, $customerId): Sale {
            $sale->customer_id = $customerId;
            $sale->save();

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
        ], 200);
    }

    /**
     * Cadastro rápido de cliente no PDV.
     */
    public function quickRegisterCustomer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'sale_id' => ['required', 'exists:sales,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $saleId = (int) $request->input('sale_id');

        $sale = Sale::where('id', $saleId)
            ->where('user_id', $user->id)
            ->where('status', SaleStatus::OPEN)
            ->with(['items.productVariant.product', 'customer', 'salePayments'])
            ->first();

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento.',
            ], 400);
        }

        $document = preg_replace('/\D/', '', $request->input('document'));
        $name = $request->input('name');

        if (! $name || trim($name) === '') {
            $name = 'Cliente ' . $document;
        }

        $existingCustomer = Customer::where('cpf_cnpj', $document)->first();

        if ($existingCustomer) {
            return response()->json([
                'message' => 'Cliente já cadastrado com este CPF/CNPJ.',
                'customer' => [
                    'id' => $existingCustomer->id,
                    'name' => $existingCustomer->name,
                    'document' => $existingCustomer->cpf_cnpj,
                ],
            ], 409);
        }

        $sale = DB::transaction(function () use ($document, $name, $sale): Sale {
            $customer = Customer::create([
                'name' => $name,
                'cpf_cnpj' => $document,
            ]);

            $sale->customer_id = $customer->id;
            $sale->save();

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
            'message' => 'Cliente cadastrado e vinculado à venda com sucesso.',
        ], 201);
    }

    /**
     * Aplica desconto na venda.
     * Para remover desconto manual (type=fixed, value=0), enviar authorized_by_user_id para auditoria.
     */
    public function applyDiscount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'authorized_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $sale = $this->getActiveSale($user);

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento.',
            ], 400);
        }

        $type = $request->input('type');
        $value = (float) $request->input('value');

        if ($type === 'percentage') {
            $maxPercent = (int) Settings::get('sales.max_discount_percent', 50);
            if ($value > $maxPercent) {
                return response()->json([
                    'message' => "Desconto excede o limite permitido de {$maxPercent}%.",
                ], 422);
            }
        }

        $authorizedByUserId = $request->has('authorized_by_user_id') ? (int) $request->input('authorized_by_user_id') : null;
        $isRemoveDiscount = $type === 'fixed' && $value == 0;

        $sale = DB::transaction(function () use ($sale, $request, $authorizedByUserId, $isRemoveDiscount): Sale {
            $type = $request->input('type');
            $value = (float) $request->input('value');

            if ($type === 'percentage') {
                $discountAmount = $sale->total_amount * ($value / 100);
            } else {
                $discountAmount = min($value, (float) $sale->total_amount);
            }

            $previousDiscount = (float) $sale->discount_amount;
            $sale->coupon_id = null;
            $sale->coupon_code = null;
            $sale->discount_amount = $discountAmount;
            $sale->final_amount = (float) $sale->total_amount - $discountAmount;
            $sale->save();

            if ($isRemoveDiscount && $authorizedByUserId) {
                ManagerAuthorizationLog::query()->create([
                    'authorized_by_user_id' => $authorizedByUserId,
                    'action' => ManagerAuthorizationLog::ACTION_REMOVE_DISCOUNT,
                    'sale_id' => $sale->id,
                    'branch_id' => $sale->branch_id,
                    'metadata' => ['previous_discount_amount' => $previousDiscount],
                ]);
            }

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
        ], 200);
    }

    /**
     * Aplica cupom promocional na venda.
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'exists:sales,id'],
            'coupon_code' => ['required', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $saleId = (int) $request->input('sale_id');
        $code = strtoupper(trim((string) $request->input('coupon_code')));

        $sale = Sale::where('id', $saleId)
            ->where('user_id', $user->id)
            ->where('status', SaleStatus::OPEN)
            ->with(['items.productVariant.product', 'customer', 'salePayments'])
            ->first();

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento.',
            ], 400);
        }

        $coupon = Coupon::with(['products', 'categories'])->where('code', $code)->first();

        if (! $coupon) {
            return response()->json([
                'message' => 'Cupom não encontrado.',
            ], 404);
        }

        if (! $coupon->active) {
            return response()->json([
                'message' => 'Cupom inativo.',
            ], 422);
        }

        $now = now();
        if ($coupon->starts_at && $now->isBefore($coupon->starts_at)) {
            return response()->json([
                'message' => 'Cupom ainda não está na validade.',
            ], 422);
        }
        if ($coupon->expires_at && $now->isAfter($coupon->expires_at)) {
            return response()->json([
                'message' => 'Cupom expirado.',
            ], 422);
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json([
                'message' => 'Cupom sem estoque de uso.',
            ], 422);
        }

        $totalAmount = (float) $sale->total_amount;
        $productIds = $coupon->products->pluck('id')->all();
        $categoryIds = $coupon->categories->pluck('id')->all();
        $hasProductOrCategoryRestriction = count($productIds) > 0 || count($categoryIds) > 0;

        $sale = DB::transaction(function () use ($sale, $coupon, $totalAmount, $productIds, $categoryIds, $hasProductOrCategoryRestriction): Sale {
            foreach ($sale->items as $item) {
                $item->discount_amount = 0;
                $item->save();
            }

            if (! $hasProductOrCategoryRestriction) {
                $baseAmount = $totalAmount;
                if ($coupon->min_purchase_amount !== null && $baseAmount < (float) $coupon->min_purchase_amount) {
                    throw new \InvalidArgumentException('O total da venda não atinge o valor mínimo para este cupom.');
                }
                $discountAmount = $coupon->type->value === 'percentage'
                    ? $baseAmount * ((float) $coupon->value / 100)
                    : (float) $coupon->value;
                if ($coupon->type->value === 'percentage' && $coupon->max_discount_amount !== null) {
                    $discountAmount = min($discountAmount, (float) $coupon->max_discount_amount);
                }
                $discountAmount = min($discountAmount, $totalAmount);
                $sale->coupon_id = $coupon->id;
                $sale->coupon_code = $coupon->code;
                $sale->discount_amount = $discountAmount;
                $sale->final_amount = (float) $sale->total_amount - $discountAmount;
                $sale->save();
                return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments', 'coupon']);
            }

            $eligibleItems = [];
            foreach ($sale->items as $item) {
                $product = $item->productVariant?->product;
                if (! $product) {
                    continue;
                }
                if ($product->hasActivePromotion()) {
                    continue;
                }
                $matchProduct = count($productIds) === 0 || in_array((int) $product->id, $productIds, true);
                $matchCategory = count($categoryIds) === 0 || ($product->category_id && in_array((int) $product->category_id, $categoryIds, true));
                if ($matchProduct || $matchCategory) {
                    $eligibleItems[] = $item;
                }
            }

            if (count($eligibleItems) === 0) {
                throw new \InvalidArgumentException('Nenhum item do carrinho é elegível a este cupom (restrição por produto/categoria ou itens já em promoção).');
            }

            $eligibleTotal = array_sum(array_map(fn ($i) => (float) $i->total_price, $eligibleItems));
            if ($coupon->min_purchase_amount !== null && $eligibleTotal < (float) $coupon->min_purchase_amount) {
                throw new \InvalidArgumentException('O total dos itens elegíveis não atinge o valor mínimo para este cupom.');
            }

            $maxDiscount = $coupon->type->value === 'percentage'
                ? $eligibleTotal * ((float) $coupon->value / 100)
                : (float) $coupon->value;
            if ($coupon->type->value === 'percentage' && $coupon->max_discount_amount !== null) {
                $maxDiscount = min($maxDiscount, (float) $coupon->max_discount_amount);
            }
            $maxDiscount = min($maxDiscount, $eligibleTotal);

            usort($eligibleItems, fn ($a, $b) => (float) $b->total_price <=> (float) $a->total_price);
            $remaining = $maxDiscount;
            foreach ($eligibleItems as $item) {
                if ($remaining <= 0) {
                    break;
                }
                $itemTotal = (float) $item->total_price;
                $itemDiscount = $coupon->type->value === 'percentage'
                    ? min($remaining, $itemTotal * ((float) $coupon->value / 100))
                    : min($remaining, $itemTotal * ($maxDiscount / $eligibleTotal));
                $itemDiscount = round(min($itemDiscount, $remaining), 2);
                $item->discount_amount = $itemDiscount;
                $item->save();
                $remaining -= $itemDiscount;
            }

            $totalDiscount = $sale->items->sum('discount_amount');
            $sale->coupon_id = $coupon->id;
            $sale->coupon_code = $coupon->code;
            $sale->discount_amount = $totalDiscount;
            $sale->final_amount = (float) $sale->total_amount - $totalDiscount;
            $sale->save();

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments', 'coupon']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
        ], 200);
    }

    /**
     * Remove o cupom aplicado à venda.
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        $user = $request->user();
        $sale = $this->getActiveSale($user);

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento.',
            ], 400);
        }

        if (! $sale->coupon_id) {
            return response()->json([
                'message' => 'Não há cupom aplicado nesta venda.',
            ], 400);
        }

        $sale = DB::transaction(function () use ($sale): Sale {
            $sale->coupon_id = null;
            $sale->coupon_code = null;
            $sale->discount_amount = 0;
            $sale->final_amount = (float) $sale->total_amount;
            $sale->save();

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments', 'coupon']);
        });

        return response()->json([
            'sale' => $this->formatSaleResponse($sale),
        ], 200);
    }

    /**
     * Adiciona pagamento à venda.
     */
    public function addPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'method' => ['required', 'string', 'in:credit_card,debit_card,cash,money,pix,store_credit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $sale = $this->getActiveSale($user);

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento.',
            ], 400);
        }

        $method = $request->input('method');
        if ($method === 'cash') {
            $method = 'money';
        }
        $validMethods = array_map(fn ($case) => $case->value, PaymentMethod::cases());
        if (! in_array($method, $validMethods, true)) {
            return response()->json([
                'message' => "Método de pagamento inválido: {$method}",
            ], 422);
        }

        if ($sale->coupon_id && $sale->coupon) {
            $allowed = $sale->coupon->allowed_payment_methods;
            if (is_array($allowed) && count($allowed) > 0 && ! in_array($method, $allowed, true)) {
                return response()->json([
                    'message' => 'Este cupom aceita apenas os seguintes pagamentos: ' . implode(', ', $allowed) . '. Remova o cupom para usar outros.',
                ], 422);
            }
        }

        $sale = DB::transaction(function () use ($sale, $request, $method): Sale {
            $amount = (float) $request->input('amount');
            $installments = (int) ($request->input('installments') ?? 1);

            SalePayment::create([
                'sale_id' => $sale->id,
                'method' => PaymentMethod::from($method),
                'amount' => $amount,
                'installments' => $installments,
            ]);

            return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments', 'coupon']);
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
     * Remove um pagamento da venda.
     * Quando autorizado por gerente, enviar authorized_by_user_id para auditoria.
     */
    public function removePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => ['required', 'integer', 'exists:sale_payments,id'],
            'authorized_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $sale = $this->getActiveSale($user);

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento.',
            ], 400);
        }

        $paymentId = (int) $request->input('payment_id');
        $authorizedByUserId = $request->has('authorized_by_user_id') ? (int) $request->input('authorized_by_user_id') : null;

        $sale = DB::transaction(function () use ($sale, $paymentId, $authorizedByUserId): Sale {
            $payment = SalePayment::where('id', $paymentId)
                ->where('sale_id', $sale->id)
                ->first();

            if (! $payment) {
                throw new \InvalidArgumentException('Pagamento não encontrado nesta venda.');
            }

            $metadata = [
                'payment_id' => $payment->id,
                'amount' => (float) $payment->amount,
                'method' => $payment->method?->value ?? (string) $payment->method,
            ];
            $payment->delete();

            if ($authorizedByUserId) {
                ManagerAuthorizationLog::query()->create([
                    'authorized_by_user_id' => $authorizedByUserId,
                    'action' => ManagerAuthorizationLog::ACTION_REMOVE_PAYMENT,
                    'sale_id' => $sale->id,
                    'branch_id' => $sale->branch_id,
                    'metadata' => $metadata,
                ]);
            }

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
     * Registra ação do gerente no PDV (ex.: visualização de saldo).
     * Usado pelo front após validar PIN+senha para auditoria.
     */
    public function logManagerAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required', 'string', 'in:view_balance'],
            'authorized_by_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $branchId = $user->branch_id;
        $cashRegister = CashRegister::where('user_id', $user->id)
            ->where('status', CashRegisterStatus::OPEN)
            ->first();

        ManagerAuthorizationLog::query()->create([
            'authorized_by_user_id' => (int) $request->input('authorized_by_user_id'),
            'action' => ManagerAuthorizationLog::ACTION_VIEW_BALANCE,
            'sale_id' => null,
            'cash_register_id' => $cashRegister?->id,
            'branch_id' => $branchId,
            'metadata' => null,
        ]);

        return response()->noContent();
    }

    /**
     * Finaliza a venda.
     * 
     * A baixa de estoque é feita automaticamente pelo SaleObserver quando o status muda para COMPLETED.
     */
    public function finish(Request $request): JsonResponse
    {
        $user = $request->user();
        $sale = $this->getActiveSale($user);

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento.',
            ], 400);
        }

        $sale->load(['items.productVariant', 'salePayments']);
        $totalPayments = $sale->salePayments->sum('amount');

        if ($totalPayments < $sale->final_amount) {
            return response()->json([
                'message' => 'Payment amount is insufficient',
                'required' => $sale->final_amount,
                'paid' => $totalPayments,
            ], 400);
        }

        try {
            $sale = DB::transaction(function () use ($sale): Sale {
                $hasPixPayment = $sale->salePayments->contains(fn (SalePayment $payment) =>
                    $payment->method === PaymentMethod::PIX
                );

                // Passo 1: Muda o status - o SaleObserver irá criar os StockMovements automaticamente
                $sale->status = $hasPixPayment ? SaleStatus::PENDING_PAYMENT : SaleStatus::COMPLETED;
                $sale->save();

                // Passo 2: Incrementa uso do cupom (se aplicável)
                if (! $hasPixPayment && $sale->coupon_id) {
                    try {
                        Coupon::where('id', $sale->coupon_id)->increment('used_count');
                    } catch (\Throwable $e) {
                        Log::error('PosController::finish - ERRO ao incrementar cupom', [
                            'sale_id' => $sale->id,
                            'coupon_id' => $sale->coupon_id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e; // Re-lança para fazer rollback
                    }
                }

                // Passo 3: Cria transação no caixa (se aplicável)
                if (! $hasPixPayment && $sale->cashRegister) {
                    try {
                        $cashRegister = $sale->cashRegister;
                        $cashRegister->transactions()->create([
                            'type' => \App\Enums\CashRegisterTransactionType::SALE,
                            'amount' => $sale->final_amount,
                            'description' => "Venda #{$sale->id}",
                            'sale_id' => $sale->id,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('PosController::finish - ERRO ao criar transação no caixa', [
                            'sale_id' => $sale->id,
                            'cash_register_id' => $sale->cashRegister->id ?? null,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e; // Re-lança para fazer rollback
                    }
                }

                return $sale->fresh(['items.productVariant.product', 'customer', 'salePayments', 'user', 'branch']);
            });

            return response()->json([
                'sale' => $this->formatSaleResponse($sale),
                'message' => 'Sale completed successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('PosController::finish - ERRO CRÍTICO AO FINALIZAR VENDA', [
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao finalizar venda: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancela a venda (muda status para 'canceled').
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $sale = $this->getActiveSale($user);

        if (! $sale) {
            return response()->json([
                'message' => 'Nenhuma venda em andamento.',
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
        $sale->loadMissing('coupon');
        if ($sale->coupon) {
            $sale->coupon->loadMissing(['products', 'categories']);
        }
        $totalPayments = $sale->salePayments->sum('amount');

        $couponPayload = null;
        if ($sale->coupon_id && $sale->coupon) {
            $c = $sale->coupon;
            $rules = [];
            if ($c->min_purchase_amount !== null) {
                $rules[] = 'Mín. compra: R$ ' . number_format((float) $c->min_purchase_amount, 2, ',', '.');
            }
            if ($c->type->value === 'percentage' && $c->max_discount_amount !== null) {
                $rules[] = 'Teto desconto: R$ ' . number_format((float) $c->max_discount_amount, 2, ',', '.');
            }
            if ($c->usage_limit !== null) {
                $rules[] = 'Usos: ' . ($c->used_count ?? 0) . '/' . $c->usage_limit;
            }
            if ($c->starts_at) {
                $rules[] = 'Válido de: ' . $c->starts_at->format('d/m/Y');
            }
            if ($c->expires_at) {
                $rules[] = 'Válido até: ' . $c->expires_at->format('d/m/Y');
            }
            $allowed = $c->allowed_payment_methods;
            if (is_array($allowed) && count($allowed) > 0) {
                $labels = [
                    'money' => 'Dinheiro',
                    'pix' => 'PIX',
                    'credit_card' => 'Cartão de Crédito',
                    'debit_card' => 'Cartão de Débito',
                    'store_credit' => 'Crédito Loja',
                ];
                $rules[] = 'Pagamento: ' . implode(', ', array_map(fn ($m) => $labels[$m] ?? $m, $allowed));
            }
            $nProducts = $c->relationLoaded('products') ? $c->products->count() : 0;
            $nCategories = $c->relationLoaded('categories') ? $c->categories->count() : 0;
            if ($nProducts > 0 || $nCategories > 0) {
                $parts = [];
                if ($nProducts > 0) {
                    $parts[] = $nProducts . ' produto(s)';
                }
                if ($nCategories > 0) {
                    $parts[] = $nCategories . ' categoria(s)';
                }
                $rules[] = 'Elegível: ' . implode(', ', $parts);
            }
            $couponPayload = [
                'id' => $c->id,
                'code' => $c->code,
                'type' => $c->type->value,
                'value' => (float) $c->value,
                'min_purchase_amount' => $c->min_purchase_amount !== null ? (float) $c->min_purchase_amount : null,
                'max_discount_amount' => $c->max_discount_amount !== null ? (float) $c->max_discount_amount : null,
                'usage_limit' => $c->usage_limit,
                'used_count' => $c->used_count,
                'starts_at' => $c->starts_at?->toIso8601String(),
                'expires_at' => $c->expires_at?->toIso8601String(),
                'allowed_payment_methods' => is_array($allowed) ? $allowed : null,
                'rules_summary' => $rules,
            ];
        }

        return [
            'id' => $sale->id,
            'user_id' => $sale->user_id,
            'branch_id' => $sale->branch_id,
            'cash_register_id' => $sale->cash_register_id,
            'customer_id' => $sale->customer_id,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
                'cpf_cnpj' => $sale->customer->cpf_cnpj,
            ] : null,
            'total_amount' => (float) $sale->total_amount,
            'discount_amount' => (float) $sale->discount_amount,
            'final_amount' => (float) $sale->final_amount,
            'status' => $sale->status->value,
            'coupon_code' => $sale->coupon_code,
            'coupon' => $couponPayload,
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
