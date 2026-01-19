<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportBatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'filename',
        'status',
        'total_rows',
        'success_count',
        'error_count',
        'errors',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ImportBatchStatus::class,
        'total_rows' => 'integer',
        'success_count' => 'integer',
        'error_count' => 'integer',
        'errors' => 'array',
    ];

    /**
     * @return BelongsTo<User, ImportBatch>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
