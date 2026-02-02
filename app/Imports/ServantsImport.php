<?php

declare(strict_types=1);

namespace App\Imports;

use App\Events\ServantImportProgress;
use App\Models\Department;
use App\Models\Position;
use App\Models\Servant;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithLimit;

class ServantsImport implements ToCollection, WithStartRow, SkipsEmptyRows, WithLimit
{
    public array $errors = [];
    public array $preview = [];
    public int $created = 0;
    public int $updated = 0;
    private array $processedLines = [];
    private bool $validateOnly = false;
    private ?int $userId = null;
    private int $totalRows = 0;
    private int $processedRows = 0;

    public function __construct(bool $validateOnly = false, ?int $userId = null)
    {
        $this->validateOnly = $validateOnly;
        $this->userId = $userId;
    }

    public function collection(Collection $rows): void
    {
        $this->totalRows = $rows->count();

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // +2 porque começa da linha 2 (depois do cabeçalho)
            $this->processedRows++;

            // Ignora linhas já processadas (evita duplicação por múltiplas sheets)
            if (isset($this->processedLines[$lineNumber])) {
                continue;
            }
            $this->processedLines[$lineNumber] = true;

            // Lê pela POSIÇÃO da coluna (índice 0-based)
            $name = trim((string) ($row[0] ?? ''));              // Coluna A: Nome Completo
            $cpf = $this->cleanCpf((string) ($row[1] ?? ''));    // Coluna B: CPF
            $rg = trim((string) ($row[2] ?? ''));                // Coluna C: RG
            $organExpeditor = trim((string) ($row[3] ?? ''));    // Coluna D: Órgão Expeditor
            $matricula = trim((string) ($row[4] ?? ''));         // Coluna E: Matrícula
            $positionId = (int) ($row[5] ?? 0);                  // Coluna F: ID Cargo/Posição
            $departmentId = (int) ($row[6] ?? 0);                // Coluna G: ID Secretaria
            $bankName = trim((string) ($row[7] ?? ''));          // Coluna H: Banco
            $agencyNumber = trim((string) ($row[8] ?? ''));      // Coluna I: Agência
            $accountNumber = trim((string) ($row[9] ?? ''));     // Coluna J: Conta
            $accountType = trim((string) ($row[10] ?? ''));      // Coluna K: Tipo de Conta
            $email = trim((string) ($row[11] ?? ''));            // Coluna L: E-mail
            $phone = trim((string) ($row[12] ?? ''));            // Coluna M: Telefone

            // Ignora linhas completamente vazias
            if (empty($name) && empty($cpf) && empty($rg) && empty($matricula)) {
                continue;
            }

            // Validação básica
            $rowErrors = [];
            if (empty($name)) $rowErrors[] = 'Nome vazio';
            if (empty($cpf)) $rowErrors[] = 'CPF vazio';
            if (empty($rg)) $rowErrors[] = 'RG vazio';
            if (empty($organExpeditor)) $rowErrors[] = 'Órgão Expeditor vazio';
            if (empty($matricula)) $rowErrors[] = 'Matrícula vazia';
            if ($positionId <= 0 || !Position::find($positionId)) {
                $rowErrors[] = 'ID Cargo/Posição inválido';
            }
            if ($departmentId <= 0 || !Department::find($departmentId)) {
                $rowErrors[] = 'ID Secretaria inválido';
            }

            if (!empty($rowErrors)) {
                $this->errors[] = [
                    'line' => $lineNumber,
                    'name' => $name ?: '(vazio)',
                    'errors' => $rowErrors,
                ];
                $this->preview[] = [
                    'line' => $lineNumber,
                    'name' => $name ?: '(vazio)',
                    'action' => 'error',
                    'errors' => $rowErrors,
                ];
                continue;
            }

            // Verifica se existe por nome completo OU CPF
            $servant = Servant::where('name', $name)
                ->orWhere('cpf', $cpf)
                ->first();

            $data = [
                'name' => $name,
                'cpf' => $cpf,
                'rg' => $rg,
                'organ_expeditor' => $organExpeditor,
                'matricula' => $matricula,
                'position_id' => $positionId,
                'department_id' => $departmentId,
                'bank_name' => $bankName ?: null,
                'agency_number' => $agencyNumber ?: null,
                'account_number' => $accountNumber ?: null,
                'account_type' => $accountType ?: null,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
            ];

            // Se for apenas validação, não salva no banco
            if ($this->validateOnly) {
                if ($servant) {
                    $this->updated++;
                    $this->preview[] = [
                        'line' => $lineNumber,
                        'name' => $name,
                        'action' => 'updated',
                        'id' => $servant->id,
                    ];
                } else {
                    $this->created++;
                    $this->preview[] = [
                        'line' => $lineNumber,
                        'name' => $name,
                        'action' => 'created',
                        'id' => null,
                    ];
                }
            } else {
                // Importação real: salva no banco
                if ($servant) {
                    $servant->update($data);
                    $this->updated++;
                    $this->preview[] = [
                        'line' => $lineNumber,
                        'name' => $name,
                        'action' => 'updated',
                        'id' => $servant->id,
                    ];
                } else {
                    $servant = Servant::create($data);
                    $this->created++;
                    $this->preview[] = [
                        'line' => $lineNumber,
                        'name' => $name,
                        'action' => 'created',
                        'id' => $servant->id,
                    ];
                }

                // Notifica progresso a cada 10 registros ou no último
                if ($this->userId && ($this->processedRows % 10 === 0 || $this->processedRows === $this->totalRows)) {
                    $progress = ($this->processedRows / $this->totalRows) * 100;
                    ServantImportProgress::dispatch($this->userId, [
                        'status' => 'processing',
                        'progress' => (int) $progress,
                        'message' => "Processando... {$this->processedRows}/{$this->totalRows}",
                        'processed' => $this->processedRows,
                        'total' => $this->totalRows,
                        'created' => $this->created,
                        'updated' => $this->updated,
                    ]);
                }
            }
        }
    }

    private function cleanCpf(string $cpf): string
    {
        return preg_replace('/[^0-9]/', '', $cpf);
    }

    /**
     * Define que a leitura começa da linha 2 (pula o cabeçalho)
     */
    public function startRow(): int
    {
        return 2;
    }

    /**
     * Limita a leitura a 1000 linhas para evitar timeout
     */
    public function limit(): int
    {
        return 1000;
    }
}
