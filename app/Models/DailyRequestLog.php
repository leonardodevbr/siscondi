<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRequestLog extends Model
{
    protected $fillable = [
        'daily_request_id',
        'user_id',
        'action',
        'ip',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function dailyRequest(): BelongsTo
    {
        return $this->belongsTo(DailyRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function logAction(
        DailyRequest $dailyRequest,
        string $action,
        ?int $userId = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'daily_request_id' => $dailyRequest->id,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
        ]);
    }
}
