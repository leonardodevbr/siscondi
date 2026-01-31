<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Store or update a push subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string|max:500',
            'keys.auth' => 'required|string',
            'keys.p256dh' => 'required|string',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth')
        );

        return response()->json(['message' => 'Inscrição de notificação registrada com sucesso.']);
    }
}
