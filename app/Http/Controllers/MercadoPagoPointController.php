<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\Payment\MercadoPagoPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoPointController extends Controller
{
    public function __construct(
        private readonly MercadoPagoPointService $pointService
    ) {
    }

    /**
     * Lista as maquininhas disponíveis para o frontend (select no PDV).
     */
    public function indexDevices(Request $request): JsonResponse
    {
        try {
            $result = $this->pointService->getDevices(
                (int) $request->query('offset', 0),
                (int) $request->query('limit', 50)
            );

            Log::info('MercadoPagoPointController::indexDevices - Sucesso', [
                'count' => count($result['devices'] ?? []),
            ]);

            return response()->json([
                'devices' => $result['devices'] ?? [],
                'paging' => $result['paging'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::warning('MercadoPagoPointController::indexDevices - Erro', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'devices' => [],
                'paging' => ['total' => 0, 'limit' => 50, 'offset' => 0],
            ], 422);
        }
    }

    /**
     * Cria o Payment Intent e envia para a maquininha (store).
     * Valida device_id e sale_id; retorna intent_id para o frontend monitorar.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'device_id' => ['required', 'string', 'max:255'],
        ]);

        $sale = Sale::find($validated['sale_id']);
        if (! $sale || $sale->status !== SaleStatus::OPEN) {
            Log::info('MercadoPagoPointController::store - Venda inválida ou já finalizada', ['sale_id' => $validated['sale_id']]);

            return response()->json([
                'message' => 'Venda não encontrada ou já foi finalizada.',
            ], 400);
        }

        try {
            $intent = $this->pointService->createPaymentIntent(
                $validated['device_id'],
                $sale
            );

            $intentId = $intent['id'] ?? null;
            if (! $intentId) {
                return response()->json(['message' => 'Resposta inválida do Mercado Pago.'], 502);
            }

            Log::info('MercadoPagoPointController::store - Intent criado', [
                'sale_id' => $sale->id,
                'intent_id' => $intentId,
                'device_id' => $validated['device_id'],
            ]);

            return response()->json([
                'intent_id' => $intentId,
                'device_id' => $validated['device_id'],
                'message' => 'Cobrança enviada para a maquininha. Aguarde o cliente inserir a senha.',
            ]);
        } catch (\Throwable $e) {
            Log::error('MercadoPagoPointController::store - Erro', [
                'sale_id' => $validated['sale_id'],
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Polling: consulta status do Payment Intent (fallback quando webhook demora ou para feedback visual).
     */
    public function checkStatus(string $intentId): JsonResponse
    {
        try {
            $intent = $this->pointService->getPaymentIntentStatus($intentId);
            Log::info('MercadoPagoPointController::checkStatus', ['intent_id' => $intentId, 'state' => $intent['state'] ?? null]);

            return response()->json([
                'intent_id' => $intentId,
                'state' => $intent['state'] ?? null,
                'additional_info' => $intent['additional_info'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MercadoPagoPointController::checkStatus - Erro', ['intent_id' => $intentId, 'message' => $e->getMessage()]);

            return response()->json([
                'message' => $e->getMessage(),
                'state' => null,
            ], 422);
        }
    }

    /**
     * Alias para manter compatibilidade com frontend que chama process-payment.
     */
    public function processPayment(Request $request): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Cancela um Payment Intent (apenas se ainda OPEN).
     */
    public function cancelIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
            'intent_id' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->pointService->cancelPaymentIntent(
                $validated['device_id'],
                $validated['intent_id']
            );

            return response()->json(['message' => 'Pagamento cancelado na maquininha.']);
        } catch (\Throwable $e) {
            Log::warning('MercadoPagoPointController::cancelIntent - Erro', ['message' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
