<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CashRegisterStatus;
use App\Enums\CashRegisterTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Coupon;
use App\Models\PixPendingCharge;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\CashRegister;
use App\Services\Payment\MercadoPagoPointService;
use App\Services\Payment\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly MercadoPagoPointService $pointService,
        private readonly MercadoPagoService $mpService
    ) {
    }

    /**
     * Webhook Mercado Pago Point: eventos payment_intent.
     * URL sugerida: POST /api/webhook/mercadopago-point
     * Configure no painel do MP em Suas integrações > Notificações.
     */
    public function handleMercadoPagoPoint(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('WebhookController::handleMercadoPagoPoint - Payload recebido', [
            'keys' => array_keys($payload),
            'type' => $payload['type'] ?? null,
            'action' => $payload['action'] ?? null,
        ]);

        if (($payload['type'] ?? null) !== 'payment_intent') {
            Log::info('WebhookController::handleMercadoPagoPoint - Tipo ignorado', ['type' => $payload['type'] ?? null]);

            return response()->json(['received' => true]);
        }

        $data = $payload['data'] ?? null;
        $id = is_array($data) ? ($data['id'] ?? null) : null;
        if (! $id) {
            Log::warning('WebhookController::handleMercadoPagoPoint - data.id ausente', ['payload' => $payload]);

            return response()->json(['received' => true]);
        }

        try {
            $intent = $this->pointService->getPaymentIntentStatus((string) $id);
            $state = $intent['state'] ?? null;
            $externalRef = ($intent['additional_info'] ?? [])['external_reference'] ?? null;

            Log::info('WebhookController::handleMercadoPagoPoint - Status do intent', [
                'intent_id' => $id,
                'state' => $state,
                'external_reference' => $externalRef,
            ]);

            $finishedStates = ['FINISHED', 'CONFIRMED'];
            if (! in_array($state, $finishedStates, true) || ! $externalRef) {
                return response()->json(['received' => true]);
            }

            $sale = Sale::with('salePayments')->find((int) $externalRef);
            if (! $sale) {
                Log::warning('WebhookController::handleMercadoPagoPoint - Venda não encontrada', ['external_reference' => $externalRef]);

                return response()->json(['received' => true]);
            }

            if ($sale->status === SaleStatus::COMPLETED) {
                Log::info('WebhookController::handleMercadoPagoPoint - Venda já completada', ['sale_id' => $sale->id]);

                return response()->json(['received' => true]);
            }

            $totalPayments = $sale->salePayments->sum('amount');
            $isFullyPaid = $totalPayments >= $sale->final_amount;

            if (! $isFullyPaid) {
                Log::info('WebhookController::handleMercadoPagoPoint - Pagamento Point aprovado, mas valor parcial', [
                    'sale_id' => $sale->id,
                    'total_payments' => $totalPayments,
                    'final_amount' => $sale->final_amount,
                ]);

                return response()->json(['received' => true]);
            }

            DB::transaction(function () use ($sale): void {
                $sale->status = SaleStatus::COMPLETED;
                $sale->save();

                if ($sale->coupon_id) {
                    Coupon::where('id', $sale->coupon_id)->increment('used_count');
                }

                if ($sale->cashRegister) {
                    $sale->cashRegister->transactions()->create([
                        'type' => CashRegisterTransactionType::SALE,
                        'amount' => $sale->final_amount,
                        'description' => "Venda #{$sale->id}",
                        'sale_id' => $sale->id,
                    ]);
                }
            });

            Log::info('WebhookController::handleMercadoPagoPoint - Venda finalizada com sucesso', [
                'sale_id' => $sale->id,
                'intent_id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::error('WebhookController::handleMercadoPagoPoint - Erro ao processar', [
                'intent_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Webhook Mercado Pago Payments (PIX etc.): tipo "payment".
     * Configure uma URL em Suas integrações > Notificações para tipo "Pagamentos".
     * POST /api/webhook/mercadopago-payment
     */
    public function handleMercadoPagoPayment(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('WebhookController::handleMercadoPagoPayment - Payload', [
            'type' => $payload['type'] ?? null,
            'action' => $payload['action'] ?? null,
        ]);

        if (($payload['type'] ?? null) !== 'payment') {
            return response()->json(['received' => true]);
        }

        $data = $payload['data'] ?? null;
        $id = is_array($data) ? ($data['id'] ?? null) : null;
        if (! $id) {
            return response()->json(['received' => true]);
        }

        $paymentId = is_numeric($id) ? (int) $id : (int) $id;
        if ($paymentId <= 0) {
            return response()->json(['received' => true]);
        }

        try {
            $payment = $this->mpService->getPayment($paymentId);
            if (! $payment || ($payment['status'] ?? '') !== 'approved') {
                return response()->json(['received' => true]);
            }

            $transactionId = (string) $paymentId;

            // Fluxo novo: cobrança PIX pendente (pos/pix/request) — frontend faz polling em charge-status
            $pendingCharge = PixPendingCharge::where('transaction_id', $transactionId)->first();

            if ($pendingCharge) {
                DB::transaction(function () use ($pendingCharge, $transactionId): void {
                    $pendingCharge->update(['status' => 'paid']);

                    SalePayment::create([
                        'sale_id' => $pendingCharge->sale_id,
                        'method' => PaymentMethod::PIX,
                        'amount' => $pendingCharge->amount,
                        'installments' => 1,
                        'transaction_id' => $transactionId,
                    ]);

                    $sale = Sale::with('salePayments', 'items', 'cashRegister')->find($pendingCharge->sale_id);
                    $totalPayments = (float) $sale->salePayments->sum('amount');
                    $isFullyPaid = $totalPayments >= (float) $sale->final_amount;

                    if ($isFullyPaid) {
                        $sale->update(['status' => SaleStatus::COMPLETED]);

                        if ($sale->coupon_id) {
                            Coupon::where('id', $sale->coupon_id)->increment('used_count');
                        }

                        $cashRegister = $sale->cashRegister ?? CashRegister::query()
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
                });

                Log::info('WebhookController::handleMercadoPagoPayment - Cobrança PIX pendente confirmada', [
                    'sale_id' => $pendingCharge->sale_id,
                    'payment_id' => $paymentId,
                ]);

                return response()->json(['received' => true]);
            }

            // Fluxo legado: venda já tinha PIX (addPayment antes de gerar QR)
            $externalRef = $payment['external_reference'] ?? '';
            if ($externalRef === '') {
                Log::warning('WebhookController::handleMercadoPagoPayment - external_reference vazio', ['payment_id' => $paymentId]);

                return response()->json(['received' => true]);
            }

            $sale = Sale::with('salePayments', 'cashRegister')->find((int) $externalRef);
            if (! $sale) {
                Log::warning('WebhookController::handleMercadoPagoPayment - Venda não encontrada', ['external_reference' => $externalRef]);

                return response()->json(['received' => true]);
            }

            if ($sale->status === SaleStatus::COMPLETED) {
                Log::info('WebhookController::handleMercadoPagoPayment - Venda já completada', ['sale_id' => $sale->id]);

                return response()->json(['received' => true]);
            }

            DB::transaction(function () use ($sale): void {
                $sale->status = SaleStatus::COMPLETED;
                $sale->save();

                if ($sale->coupon_id) {
                    Coupon::where('id', $sale->coupon_id)->increment('used_count');
                }

                if ($sale->cashRegister) {
                    $sale->cashRegister->transactions()->create([
                        'type' => CashRegisterTransactionType::SALE,
                        'amount' => $sale->final_amount,
                        'description' => "Venda #{$sale->id}",
                        'sale_id' => $sale->id,
                    ]);
                }
            });

            Log::info('WebhookController::handleMercadoPagoPayment - Venda finalizada', [
                'sale_id' => $sale->id,
                'payment_id' => $paymentId,
            ]);
        } catch (\Throwable $e) {
            Log::error('WebhookController::handleMercadoPagoPayment - Erro', [
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json(['received' => true]);
    }
}
