<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ServantImportProgress;
use App\Imports\ServantsImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Processa importação na fila (queue:work via cron).
 * Notifica progresso via WebSocket a cada 5 itens e persiste em cache.
 * E-mails são enfileirados (Mail::queue) para processamento em background.
 */
class ImportServantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CACHE_KEY_PREFIX = 'servant_import_progress_';
    private const CACHE_TTL = 3600;

    public function __construct(
        private string $filePath,
        private int $userId
    ) {}

    public function handle(): void
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $this->userId;

        try {
            $payload = [
                'status' => 'processing',
                'progress' => 0,
                'message' => 'Iniciando importação...',
            ];
            Cache::put($cacheKey, $payload, self::CACHE_TTL);
            ServantImportProgress::dispatch($this->userId, $payload);

            $import = new ServantsImport(validateOnly: false, userId: $this->userId);
            Excel::import($import, Storage::path($this->filePath));

            if (!empty($import->errors)) {
                $payload = [
                    'status' => 'error',
                    'progress' => 100,
                    'message' => 'Importação concluída com erros.',
                    'errors' => $import->errors,
                    'summary' => [
                        'created' => $import->created,
                        'updated' => $import->updated,
                        'errors_count' => count($import->errors),
                    ],
                ];
            } else {
                $payload = [
                    'status' => 'completed',
                    'progress' => 100,
                    'message' => 'Importação concluída com sucesso!',
                    'summary' => [
                        'created' => $import->created,
                        'updated' => $import->updated,
                        'total' => $import->created + $import->updated,
                    ],
                ];
            }

            ServantImportProgress::dispatch($this->userId, $payload);
            Storage::delete($this->filePath);
            
            // Limpa cache após 10s para não persistir após conclusão
            Cache::put($cacheKey, $payload, 10);
        } catch (\Exception $e) {
            $payload = [
                'status' => 'error',
                'progress' => 0,
                'message' => 'Erro ao processar importação.',
                'error' => $e->getMessage(),
            ];
            ServantImportProgress::dispatch($this->userId, $payload);
            Storage::delete($this->filePath);
            
            // Limpa cache após 10s
            Cache::put($cacheKey, $payload, 10);
        }
    }
}
