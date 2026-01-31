<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;

class ConfigController extends Controller
{
    /**
     * Lista todas as roles do banco para uso em selects (cargos, usuários, etc.).
     */
    public function roles(): JsonResponse
    {
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'data' => $roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values()->all(),
        ]);
    }
    /**
     * Configurações públicas para o frontend (nome do sistema, dados do município).
     * Nome do sistema: settings app_name ou .env APP_NAME.
     * Dados do município: do usuário logado (users.municipality_id); se não logado, primeiro município.
     */
    public function publicConfig(): JsonResponse
    {
        $appName = Settings::get('app_name') ?: Config::get('app.name');
        $user = auth('sanctum')->user();
        $municipality = $user?->getMunicipality() ?? Municipality::query()->first();

        return response()->json([
            'app_name' => $appName,
            'vapid_public_key' => config('webpush.vapid.public_key') ?: null,
            'municipality' => $municipality ? [
                'id' => $municipality->id,
                'name' => $municipality->name,
                'display_name' => $municipality->display_name,
                'state' => $municipality->state,
                'address' => $municipality->address,
                'email' => $municipality->email,
                'cnpj' => $municipality->cnpj,
                'logo_path' => $municipality->logo_path,
            ] : [],
        ]);
    }
}
