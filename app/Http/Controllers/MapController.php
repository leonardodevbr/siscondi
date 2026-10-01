<?php

namespace App\Http\Controllers;

use App\Models\CulturalAgent;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function points(Request $request)
    {
        $municipalityId = $request->user()->municipality_id;

        $data = CulturalAgent::query()
            ->where('municipality_id', $municipalityId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'name', 'artistic_name', 'latitude', 'longitude'])
            ->map(fn ($agent) => [
                'id' => $agent->id,
                'type' => 'agent',
                'type_label' => 'Agente cultural',
                'name' => $agent->artistic_name ?: $agent->name,
                'latitude' => $agent->latitude,
                'longitude' => $agent->longitude,
            ]);

        return response()->json(['data' => $data]);
    }
}
