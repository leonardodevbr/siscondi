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
        return (new WebPushMessage)
            ->title('Nova Solicitação de Diária')
            ->body('O servidor ' . $this->dailyRequest->servant->name . ' solicitou uma diária.')
            ->action('Ver Solicitação', 'view_request')
            ->data(['url' => '/daily-requests/' . $this->dailyRequest->id]);
    }
}
