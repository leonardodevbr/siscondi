<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CashRegisterTransactionType;
use App\Enums\SaleStatus;
use App\Models\Coupon;
use App\Models\Sale;
use App\Services\Payment\MercadoPagoPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly MercadoPagoPointService $pointService
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

            $sale = Sale::find((int) $externalRef);
            if (! $sale) {
                Log::warning('WebhookController::handleMercadoPagoPoint - Venda não encontrada', ['external_reference' => $externalRef]);

                return response()->json(['received' => true]);
            }

            if ($sale->status === SaleStatus::COMPLETED) {
                Log::info('WebhookController::handleMercadoPagoPoint - Venda já completada', ['sale_id' => $sale->id]);

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
}
