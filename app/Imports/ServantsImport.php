<?php

declare(strict_types=1);

namespace App\Imports;

use App\Events\ServantImportProgress;
use App\Mail\FirstAccessMail;
use App\Models\Department;
use App\Models\Position;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
            $username = trim((string) ($row[12] ?? ''));          // Coluna M: Username (vazio = gera primeiro.ultimo)
            $phone = trim((string) ($row[13] ?? ''));             // Coluna N: Telefone

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
                // Importação real: salva no banco e cria/atualiza User com e-mail (primeiro acesso)
                $servant = DB::transaction(function () use ($data, $servant, $name, $email, $username, $departmentId) {
                    $userId = null;
                    if (!empty($email)) {
                        $existingUser = User::where('email', $email)->first();
                        $department = Department::find($departmentId);
                        $municipalityId = $department?->municipality_id;

                        $usernameToSet = $username !== ''
                            ? $username
                            : $this->usernameFromName($name);
                        $usernameToSet = $this->ensureUniqueUsername($usernameToSet, $existingUser?->id);

                        if ($existingUser) {
                            $userId = $existingUser->id;
                            $existingUser->update([
                                'name' => $name,
                                'username' => $usernameToSet,
                                'municipality_id' => $municipalityId,
                            ]);
                            if ($servant && (int) $servant->user_id !== (int) $userId) {
                                $existingUser->departments()->sync([$departmentId => ['is_primary' => true]]);
                                $existingUser->update(['primary_department_id' => $departmentId]);
                            }
                        } else {
                            $newUser = User::create([
                                'name' => $name,
                                'email' => $email,
                                'username' => $usernameToSet,
                                'password' => Str::random(32),
                                'municipality_id' => $municipalityId,
                            ]);
                            $newUser->syncRoles(['beneficiary']);
                            $newUser->departments()->attach($departmentId, ['is_primary' => true]);
                            $newUser->update(['primary_department_id' => $departmentId]);
                            $userId = $newUser->id;

                            try {
                                $token = Password::broker()->createToken($newUser);
                                $resetUrl = rtrim(config('app.url'), '/') . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email);
                                Mail::to($email)->send(new FirstAccessMail($newUser, $resetUrl, true));
                            } catch (\Throwable $e) {
                                report($e);
                            }
                        }
                    }

                    $data['user_id'] = $userId;
                    if ($servant) {
                        $servant->update($data);
                        return $servant;
                    }
                    return Servant::create($data);
                });

                if ($servant->wasRecentlyCreated) {
                    $this->created++;
                } else {
                    $this->updated++;
                }
                $this->preview[] = [
                    'line' => $lineNumber,
                    'name' => $name,
                    'action' => $servant->wasRecentlyCreated ? 'created' : 'updated',
                    'id' => $servant->id,
                ];

                // Notifica a cada 1% de progresso (ou a cada 2 itens, o que for menor)
                $notifyInterval = max(1, (int) ($this->totalRows / 100));
                $shouldNotify = ($this->processedRows % $notifyInterval === 0) || ($this->processedRows === $this->totalRows);

                if ($this->userId && $shouldNotify) {
                    $progress = $this->totalRows > 0 ? (int) (($this->processedRows / $this->totalRows) * 100) : 0;
                    $payload = [
                        'status' => 'processing',
                        'progress' => $progress,
                        'message' => "Processando... {$this->processedRows}/{$this->totalRows}",
                        'processed' => $this->processedRows,
                        'total' => $this->totalRows,
                        'created' => $this->created,
                        'updated' => $this->updated,
                    ];
                    Cache::put('servant_import_progress_' . $this->userId, $payload, 3600);
                    ServantImportProgress::dispatch($this->userId, $payload);
                }
            }
        }
    }

    private function cleanCpf(string $cpf): string
    {
        return preg_replace('/[^0-9]/', '', $cpf);
    }

    /**
     * Gera username no formato FIRSTNAME.LASTNAME: minúsculo, sem acentos.
     * Ex: "João Silva Canção" => "joao.cancao"
     */
    private function usernameFromName(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) === 0) {
            return 'user';
        }
        if (count($words) === 1) {
            return $this->slugPart($words[0]);
        }
        $first = $this->slugPart($words[0]);
        $last = $this->slugPart($words[count($words) - 1]);

        return $first . '.' . $last;
    }

    /**
     * Minúsculo, sem acentos, apenas letras/números.
     */
    private function slugPart(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = Str::ascii($s);
        $s = preg_replace('/[^a-z0-9]/', '', $s);

        return $s !== '' ? $s : 'user';
    }

    /**
     * Garante username único na tabela users (evita conflito na coluna unique).
     */
    private function ensureUniqueUsername(string $username, ?int $excludeUserId): string
    {
        $base = $username;
        $n = 1;
        while (true) {
            $exists = User::where('username', $username)
                ->when($excludeUserId !== null, fn ($q) => $q->where('id', '!=', $excludeUserId))
                ->exists();
            if (! $exists) {
                return $username;
            }
            $n++;
            $username = $base . (string) $n;
        }
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
