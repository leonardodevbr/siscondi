<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CulturalAgent;
use App\Models\Manifestation;
use App\Models\SyncOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $municipalityId = $request->user()->municipality_id;

        return response()->json([
            'agents' => CulturalAgent::where('municipality_id', $municipalityId)->count(),
            'communities' => Community::where('municipality_id', $municipalityId)->count(),
            'manifestations' => Manifestation::whereHas('culturalAgents', fn ($q) => $q->where('municipality_id', $municipalityId))->distinct()->count(),
            'projects' => 0,
            'pending_sync' => SyncOperation::where('municipality_id', $municipalityId)->where('status', 'conflict')->count(),
        ]);
    }
}
