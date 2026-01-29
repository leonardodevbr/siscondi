<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    private function ensureSuperAdmin(): void
    {
        if (! auth()->user()?->hasRole('super-admin')) {
            abort(403, 'Apenas Super Admin pode acessar as configurações do sistema.');
        }
    }

    public function index(): JsonResponse
    {
        $this->ensureSuperAdmin();
        $this->authorize('settings.manage');

        $settings = Setting::query()
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group')
            ->map(function ($groupSettings) {
                return $groupSettings->map(function (Setting $setting): array {
                    $value = $setting->getTypedValue();
                    $isMasked = in_array($setting->key, Setting::MASKED_KEYS, true)
                        && $value !== null
                        && $value !== '';

                    return [
                        'key' => $setting->key,
                        'value' => $isMasked ? null : $value,
                        'type' => $setting->type,
                        'masked' => $isMasked,
                    ];
                })->values();
            });

        return response()->json($settings);
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $this->ensureSuperAdmin();
        $data = $request->validated();

        foreach ($data['settings'] as $item) {
            $key = $item['key'] ?? null;
            if (! is_string($key)) {
                continue;
            }
            if (in_array($key, Setting::MASKED_KEYS, true)) {
                $v = $item['value'] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
            }

            /** @var Setting $setting */
            $setting = Setting::query()->firstOrNew(['key' => $key]);

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

        return response()->json(['message' => 'Configurações atualizadas com sucesso.']);
    }
}
