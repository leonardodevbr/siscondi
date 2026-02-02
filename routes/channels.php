<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (Pusher / WebSocket)
|--------------------------------------------------------------------------
| Quem pode assinar: qualquer usuário que possa ver solicitações (dashboard,
| lista e bandeija de notificações atualizam em tempo real).
*/

Broadcast::channel('daily-requests-pending', function (User $user): bool {
    return $user->can('daily-requests.view');
});
