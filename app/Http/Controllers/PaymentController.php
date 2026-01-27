<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\CashRegister;
use App\Models\Coupon;
use App\Models\PixPendingCharge;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Services\Payment\DTOs\PaymentData;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller unificado para processar pagamentos através de qualquer gateway.
 * O operador do PDV não precisa saber qual gateway está ativo.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayFactory $gatewayFactory
    ) {
    }

    /**
     * Simula parcelas para cartão de crédito.
     * GET /api/payments/simulate-installments?amount=100.00
     *
     * @return JsonResponse
     */
    public function simulateInstallments(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $amount = (float) $request->input('amount');
        $branchId = $request->input('branch_id') ? (int) $request->input('branch_id') : null;

        try {
            $gateway = $this->gatewayFactory->getGateway($branchId);
            $installments = $gateway->calculateInstallments($amount);

            return response()->json(['installments' => $installments], 200);
        } catch (\Throwable $e) {
            Log::error('PaymentController::simulateInstallments failed', [
                'amount' => $amount,
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Processa um pagamento (PIX, Cartão, etc).
     * POST /api/payments/process
     *
     * Body:
     * {
     *   "sale_id": 123,
     *   "method": "pix",
     *   "amount": 100.00,
     *   "installments": 1
     * }
     *
     * @return JsonResponse
     */
    public function process(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'method' => ['required', 'string', 'in:pix,credit_card,debit_card,money'],
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
        $saleId = (int) $request->input('sale_id');
        $method = $request->input('method');
        $amount = (float) $request->input('amount');
        $installments = (int) ($request->input('installments') ?? 1);

        $sale = Sale::where('id', $saleId)
            ->where('user_id', $user->id)
            ->with('salePayments')
            ->first();

        if (! $sale) {
            return response()->json(['message' => 'Venda não encontrada.'], 404);
        }

        if ($sale->status !== SaleStatus::PENDING_PAYMENT && $sale->status !== SaleStatus::OPEN) {
            return response()->json([
                'message' => 'A venda não está aguardando pagamento.',
            ], 400);
        }

        try {
            $gateway = $this->gatewayFactory->getGateway($sale->branch_id);

            $paymentData = new PaymentData(
                saleId: $saleId,
                amount: $amount,
                method: PaymentMethod::from($method),
                installments: $installments,
                description: "Venda #{$saleId}",
                payerEmail: $sale->customer?->email ?? 'cliente@pdv.com.br',
            );

            $paymentResponse = $gateway->createPayment($paymentData);

            // Atualiza o SalePayment com o transaction_id
            $salePayment = $sale->salePayments()
                ->where('method', PaymentMethod::from($method))
                ->first();

            if ($salePayment) {
                $salePayment->update([
                    'transaction_id' => $paymentResponse->transactionId,
                ]);
            }

            return response()->json($paymentResponse->toArray(), 200);
        } catch (\Throwable $e) {
            Log::error('PaymentController::process failed', [
                'sale_id' => $saleId,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook unificado para processar notificações de pagamento.
     * POST /api/webhooks/{gateway}
     *
     * @param string $gateway Nome do gateway ('mercadopago' ou 'pagbank')
     * @return JsonResponse
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        try {
            $gatewayInstance = $this->gatewayFactory->getGatewayByName($gateway);
            $webhookData = $gatewayInstance->webhookHandler($request);

            if (! $webhookData) {
                return response()->json([
                    'message' => 'Invalid webhook payload.',
                ], 400);
            }

            return DB::transaction(function () use ($webhookData): JsonResponse {
                $transactionId = $webhookData['transaction_id'];
                $status = $webhookData['status'];
                $saleId = (int) ($webhookData['sale_id'] ?? 0);

                // Fluxo novo: cobrança PIX pendente (pagamento ainda não foi addPayment)
                $pendingCharge = PixPendingCharge::where('transaction_id', $transactionId)->first();

                if ($pendingCharge && $status === 'approved') {
                    $pendingCharge->update(['status' => 'paid']);

                    SalePayment::create([
                        'sale_id' => $pendingCharge->sale_id,
                        'method' => PaymentMethod::PIX,
                        'amount' => $pendingCharge->amount,
                        'installments' => 1,
                        'transaction_id' => $transactionId,
                    ]);

                    $sale = Sale::with('salePayments', 'items')->find($pendingCharge->sale_id);
                    $totalPayments = $sale->salePayments->sum('amount');
                    $isFullyPaid = $totalPayments >= (float) $sale->final_amount;

                    if ($isFullyPaid) {
                        $reason = "Venda #{$sale->id}";
                        $movementsAlreadyExist = StockMovement::where('reason', $reason)->exists();
                        if (! $movementsAlreadyExist) {
                            foreach ($sale->items as $item) {
                                StockMovement::create([
                                    'branch_id' => $sale->branch_id,
                                    'product_variant_id' => $item->product_variant_id,
                                    'type' => \App\Enums\StockMovementType::SALE,
                                    'quantity' => $item->quantity,
                                    'reason' => $reason,
                                    'user_id' => $sale->user_id,
                                ]);
                            }
                        }
                        $sale->update(['status' => SaleStatus::COMPLETED]);
                        if ($sale->coupon_id) {
                            Coupon::where('id', $sale->coupon_id)->increment('used_count');
                        }
                        $cashRegister = CashRegister::query()
                            ->where('user_id', $sale->user_id)
                            ->where('status', CashRegisterStatus::OPEN)
                            ->first();
                        if ($cashRegister) {
                            $cashRegister->transactions()->create([
                                'type' => CashRegisterTransactionType::SALE,
                                'amount' => (float) $sale->final_amount,
                                'description' => "Venda #{$sale->id}",
                                'sale_id' => $sale->id,
                            ]);
                        }
                    }

                    return response()->json(['message' => 'Webhook processed successfully.'], 200);
                }

                // Fluxo legado: SalePayment já existia (ex.: PIX gerado por generatePix)
                $salePayment = SalePayment::where('transaction_id', $transactionId)->first();

                if (! $salePayment) {
                    Log::warning('PaymentController::webhook - SalePayment/PendingCharge não encontrado', [
                        'transaction_id' => $transactionId,
                        'sale_id' => $saleId,
                    ]);

                    return response()->json(['message' => 'Payment not found.'], 404);
                }

                $sale = $salePayment->sale;

                if ($status === 'approved') {
                    $sale->load('items');
                    $reason = "Venda #{$sale->id}";
                    $movementsAlreadyExist = StockMovement::where('reason', $reason)->exists();

                    if (! $movementsAlreadyExist) {
                        foreach ($sale->items as $item) {
                            StockMovement::create([
                                'branch_id' => $sale->branch_id,
                                'product_variant_id' => $item->product_variant_id,
                                'type' => \App\Enums\StockMovementType::SALE,
                                'quantity' => $item->quantity,
                                'reason' => $reason,
                                'user_id' => $sale->user_id,
                            ]);
                        }
                    }

                    $sale->update(['status' => SaleStatus::COMPLETED]);

                    if ($sale->coupon_id) {
                        Coupon::where('id', $sale->coupon_id)->increment('used_count');
                    }

                    $cashRegister = CashRegister::query()
                        ->where('user_id', $sale->user_id)
                        ->where('status', CashRegisterStatus::OPEN)
                        ->first();

                    if ($cashRegister) {
                        $cashRegister->transactions()->create([
                            'type' => CashRegisterTransactionType::SALE,
                            'amount' => (float) $salePayment->amount,
                            'description' => "Venda #{$sale->id} - {$salePayment->method->value}",
                            'sale_id' => $sale->id,
                        ]);
                    }
                } elseif ($status === 'rejected') {
                    Log::info('PaymentController::webhook - Pagamento rejeitado', [
                        'transaction_id' => $transactionId,
                        'sale_id' => $sale->id,
                    ]);
                }

                return response()->json(['message' => 'Webhook processed successfully.'], 200);
            });
        } catch (\Throwable $e) {
            Log::error('PaymentController::webhook failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Webhook processing failed.',
            ], 500);
        }
    }
}
