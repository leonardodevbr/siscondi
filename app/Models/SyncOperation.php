<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncOperation extends Model
{
    protected $fillable = [
        'municipality_id', 'user_id', 'client_uuid', 'resource', 'action',
        'payload_hash', 'status', 'result_reference', 'conflict_payload'
    ];

    protected $casts = ['conflict_payload' => 'array'];
}
