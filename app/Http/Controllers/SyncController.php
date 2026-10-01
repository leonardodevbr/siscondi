<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CulturalAgent;
use App\Models\SyncOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function batch(Request $request)
    {
        $data = $request->validate([
            'operations' => ['required', 'array', 'max:100'],
            'operations.*.id' => ['required', 'uuid'],
            'operations.*.resource' => ['required', 'string'],
            'operations.*.action' => ['required', 'string'],
            'operations.*.payload' => ['required', 'array'],
        ]);

        $results = [];

        foreach ($data['operations'] as $operation) {
            $existing = SyncOperation::where('client_uuid', $operation['id'])->first();
            if ($existing) {
                $results[] = ['id' => $operation['id'], 'status' => 'duplicate', 'reference' => $existing->result_reference];
                continue;
            }

            $payload = $operation['payload'];
            $hash = hash('sha256', json_encode($payload));
            $municipalityId = $request->user()->municipality_id;

            if ($operation['resource'] === 'cultural-agent' && $operation['action'] === 'create') {
                $possibleDuplicate = CulturalAgent::query()
                    ->where('municipality_id', $municipalityId)
                    ->where(function ($q) use ($payload) {
                        $q->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($payload['name'] ?? ''))]);
                        if (! empty($payload['artistic_name'])) {
                            $q->orWhereRaw('LOWER(artistic_name) = ?', [mb_strtolower(trim($payload['artistic_name']))]);
                        }
                        if (! empty($payload['phone'])) {
                            $q->orWhere('phone', $payload['phone']);
                        }
                    })
                    ->first();

                if ($possibleDuplicate) {
                    SyncOperation::create([
                        'municipality_id' => $municipalityId,
                        'user_id' => $request->user()->id,
                        'client_uuid' => $operation['id'],
                        'resource' => $operation['resource'],
                        'action' => $operation['action'],
                        'payload_hash' => $hash,
                        'status' => 'conflict',
                        'result_reference' => 'cultural-agent:'.$possibleDuplicate->id,
                        'conflict_payload' => ['incoming' => $payload, 'existing_id' => $possibleDuplicate->id],
                    ]);
                    $results[] = ['id' => $operation['id'], 'status' => 'conflict', 'existing_id' => $possibleDuplicate->id];
                    continue;
                }

                $agent = DB::transaction(function () use ($payload, $municipalityId) {
                    $communityId = null;
                    if (! empty($payload['community_name'])) {
                        $communityId = Community::firstOrCreate([
                            'municipality_id' => $municipalityId,
                            'name' => trim($payload['community_name']),
                        ])->id;
                    }

                    return CulturalAgent::create([
                        'municipality_id' => $municipalityId,
                        'community_id' => $communityId,
                        'name' => $payload['name'],
                        'artistic_name' => $payload['artistic_name'] ?? null,
                        'phone' => $payload['phone'] ?? null,
                        'latitude' => $payload['latitude'] ?? null,
                        'longitude' => $payload['longitude'] ?? null,
                        'biography' => $payload['notes'] ?? null,
                        'status' => 'active',
                    ]);
                });

                SyncOperation::create([
                    'municipality_id' => $municipalityId,
                    'user_id' => $request->user()->id,
                    'client_uuid' => $operation['id'],
                    'resource' => $operation['resource'],
                    'action' => $operation['action'],
                    'payload_hash' => $hash,
                    'status' => 'synced',
                    'result_reference' => 'cultural-agent:'.$agent->id,
                ]);

                $results[] = ['id' => $operation['id'], 'status' => 'synced', 'reference' => 'cultural-agent:'.$agent->id];
                continue;
            }

            $results[] = ['id' => $operation['id'], 'status' => 'unsupported'];
        }

        return response()->json(['results' => $results]);
    }
}
