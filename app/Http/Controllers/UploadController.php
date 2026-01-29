<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Upload de logo/brasão para department ou municipality.
     * Grava em storage/app/public (disco padrão public).
     * POST /upload/logo com: file (imagem), type (department|municipality), id (opcional para criar)
     */
    public function logo(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(['super-admin', 'admin'])) {
            abort(403, 'Sem permissão para enviar logo.');
        }

        $request->validate([
            'file' => ['required', 'file', 'image', 'max:2048'],
            'type' => ['required', 'string', 'in:department,municipality'],
            'id' => ['required', 'string'],
        ]);

        $file = $request->file('file');
        $type = $request->input('type');
        $id = $request->input('id');

        $folder = "logos/{$type}/{$id}";
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs($folder, $name, 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
