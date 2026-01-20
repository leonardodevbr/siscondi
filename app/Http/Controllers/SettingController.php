<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
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

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $data = $request->validated();

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

