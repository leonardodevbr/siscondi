<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('settings.manage');

        $settings = Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(function ($groupSettings) {
                return $groupSettings->map(fn (Setting $setting) => [
                    'key' => $setting->key,
                    'value' => $setting->getTypedValue(),
                    'type' => $setting->type,
                ])->values();
            });

        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('settings.manage');

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:100'],
            'settings.*.value' => ['nullable'],
            'settings.*.group' => ['nullable', 'string', 'max:50'],
            'settings.*.type' => ['nullable', 'string', 'in:string,boolean,integer,json'],
        ]);

        foreach ($data['settings'] as $item) {
            /** @var Setting $setting */
            $setting = Setting::query()->firstOrNew(['key' => $item['key']]);

            if (isset($item['group'])) {
                $setting->group = $item['group'];
            } elseif (! $setting->exists) {
                $setting->group = 'general';
            }

            if (isset($item['type'])) {
                $setting->type = $item['type'];
            } elseif (! $setting->exists) {
                $setting->type = 'string';
            }

            $setting->setTypedValue($item['value'] ?? null);
            $setting->save();
        }

        Cache::forget('settings.all');

        return response()->json(['message' => 'Settings updated successfully.']);
    }
}

