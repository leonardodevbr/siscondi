<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServantImportProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public array $data
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('servant-import.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import.progress';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
