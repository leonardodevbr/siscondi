<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerAuthorizationLog extends Model
{
    protected $table = 'manager_authorization_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'authorized_by_user_id',
        'action',
        'sale_id',
        'cash_register_id',
        'branch_id',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    public const ACTION_CANCEL_ITEM = 'cancel_item';

    public const ACTION_REMOVE_DISCOUNT = 'remove_discount';

    public const ACTION_VIEW_BALANCE = 'view_balance';

    public const ACTION_REMOVE_PAYMENT = 'remove_payment';

    /**
     * @return BelongsTo<User, $this>
     */
    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
