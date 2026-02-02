<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DailyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DailyRequestPendingNotification extends Notification
{
    public function __construct(
        public DailyRequest $dailyRequest
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $requester = $this->dailyRequest->requester?->name ?? 'Um usuário';
        $servant = $this->dailyRequest->servant?->name ?? 'servidor';

        return (new WebPushMessage)
            ->title('Nova Solicitação de Diária')
            ->body($requester . ' fez uma solicitação de diária para o servidor ' . $servant . '.')
            ->action('Ver Solicitação', 'view_request')
            ->data(['url' => '/daily-requests/' . $this->dailyRequest->id]);
    }
}
