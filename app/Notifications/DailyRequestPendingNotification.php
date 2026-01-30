<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DailyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DailyRequestPendingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DailyRequest $dailyRequest
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $servantName = $this->dailyRequest->servant?->name ?? 'Servidor';
        $status = $this->dailyRequest->status->label();

        return (new WebPushMessage)
            ->title('SISCONDI – Solicitação de diária')
            ->body("Solicitação #{$this->dailyRequest->id} – {$servantName} ({$status}). Pendente de sua assinatura.")
            ->icon('/favicon/favicon-96x96.png')
            ->badge('/favicon/favicon-96x96.png')
            ->tag('daily-request-' . $this->dailyRequest->id)
            ->data(['url' => '/daily-requests']);
    }
}
