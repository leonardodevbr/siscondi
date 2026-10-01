<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CulturalAgent;
use Illuminate\Http\Request;

class CulturalAgentController extends Controller
{
    public function index(Request $request)
    {
        $municipalityId = $request->user()->municipality_id;
        $query = CulturalAgent::query()
            ->with(['community:id,name', 'manifestations:id,name,category'])
            ->where('municipality_id', $municipalityId)
            ->latest();

        if ($request->filled('q')) {
            $q = (string) $request->string('q');
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                ->orWhere('artistic_name', 'like', "%{$q}%"));
        }

        return response()->json($query->paginate(30));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'artistic_name' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:180'],
            'community_name' => ['nullable', 'string', 'max:180'],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'biography' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $municipalityId = $request->user()->municipality_id;

        if (empty($data['community_id']) && ! empty($data['community_name'])) {
            $community = Community::firstOrCreate([
                'municipality_id' => $municipalityId,
                'name' => trim($data['community_name']),
            ]);
            $data['community_id'] = $community->id;
        }

        unset($data['community_name'], $data['notes']);
        $data['municipality_id'] = $municipalityId;
        $data['status'] = 'active';

        $agent = CulturalAgent::create($data);

        return response()->json($agent->load('community'), 201);
    }
}
