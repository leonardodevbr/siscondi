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
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportServantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $filePath,
        private int $userId
    ) {}

    public function handle(): void
    {
        try {
            // Notifica início
            ServantImportProgress::dispatch($this->userId, [
                'status' => 'processing',
                'progress' => 0,
                'message' => 'Iniciando importação...',
            ]);

            // Processa a importação
            $import = new ServantsImport(validateOnly: false);
            Excel::import($import, Storage::path($this->filePath));

            // Se houve erros, notifica
            if (!empty($import->errors)) {
                ServantImportProgress::dispatch($this->userId, [
                    'status' => 'error',
                    'progress' => 100,
                    'message' => 'Importação concluída com erros.',
                    'errors' => $import->errors,
                    'summary' => [
                        'created' => $import->created,
                        'updated' => $import->updated,
                        'errors_count' => count($import->errors),
                    ],
                ]);
            } else {
                // Notifica sucesso
                ServantImportProgress::dispatch($this->userId, [
                    'status' => 'completed',
                    'progress' => 100,
                    'message' => 'Importação concluída com sucesso!',
                    'summary' => [
                        'created' => $import->created,
                        'updated' => $import->updated,
                        'total' => $import->created + $import->updated,
                    ],
                ]);
            }

            // Remove arquivo temporário
            Storage::delete($this->filePath);
        } catch (\Exception $e) {
            // Notifica erro
            ServantImportProgress::dispatch($this->userId, [
                'status' => 'error',
                'progress' => 0,
                'message' => 'Erro ao processar importação.',
                'error' => $e->getMessage(),
            ]);

            // Remove arquivo temporário
            Storage::delete($this->filePath);
        }
    }
}
