<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Position;
use App\Models\Department;
use App\Models\Servant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ServantSeeder extends Seeder
{
    /**
     * Seeder de Servidores - Versão Limpa
     * 
     * Este seeder está preparado para:
     * 1. Importação via planilha Excel (recomendado)
     * 2. Criação manual de servidores conforme necessário
     * 
     * Para importar servidores:
     * - Use a planilha: servidores_cafarnaum_completo.xlsx
     * - Execute o comando de importação do sistema
     */
    public function run(): void
    {
        $this->command->info('ServantSeeder: Iniciando...');

        // Buscar dados necessários
        $departments = Department::all();
        $positions = Position::all();

        if ($departments->isEmpty()) {
            $this->command->warn('ServantSeeder: Nenhuma secretaria encontrada. Execute DepartmentSeeder antes.');
            return;
        }

        if ($positions->isEmpty()) {
            $this->command->warn('ServantSeeder: Nenhum cargo encontrado. Execute PositionSeeder antes.');
            return;
        }

        // Buscar roles para atribuição automática
        $beneficiaryRole = Role::findByName('beneficiary');
        $validatorRole = Role::findByName('validator');
        $payerRole = Role::findByName('payer');

        /**
         * EXEMPLO DE CRIAÇÃO DE SERVIDOR
         * 
         * Descomente e adapte o código abaixo para criar servidores manualmente
         * ou use a importação via planilha Excel (recomendado)
         */
        
        /*
        $exampleServidor = [
            'cpf' => '12345678901',
            'matricula' => '001',
            'name' => 'Nome Completo do Servidor',
            'rg' => '1234567',
            'organ_expeditor' => 'SSP/BA',
            'email' => 'servidor@municipio.gov.br',
            'position_symbol' => 'CC-01',  // Símbolo do cargo
            'department_code' => 'GAB',     // Código do departamento
            'decree_number' => '001/2025',
            'decree_date' => '2025-01-01',
        ];

        $this->createServant($exampleServidor, $positions, $departments, $beneficiaryRole, $validatorRole, $payerRole);
        */

        $this->command->info('✓ ServantSeeder concluído.');
        $this->command->newLine();
        $this->command->info('ℹ️  Para popular servidores:');
        $this->command->info('   1. Use a planilha: servidores_cafarnaum_completo.xlsx');
        $this->command->info('   2. Importe via interface do sistema');
        $this->command->info('   3. Ou adicione servidores manualmente neste seeder');
    }

    /**
     * Cria um servidor e seu usuário associado
     */
    private function createServant(
        array $data,
        $positions,
        $departments,
        $beneficiaryRole,
        $validatorRole,
        $payerRole
    ): void {
        // Buscar cargo
        $position = $positions->firstWhere('symbol', $data['position_symbol']);
        if (!$position) {
            $this->command->warn("Cargo {$data['position_symbol']} não encontrado para {$data['name']}");
            return;
        }

        // Buscar departamento
        $department = $departments->firstWhere('code', $data['department_code']);
        if (!$department) {
            $this->command->warn("Departamento {$data['department_code']} não encontrado para {$data['name']}");
            return;
        }

        // Verificar se já existe usuário com este email
        $user = User::where('email', $data['email'])->first();
        
        if (!$user) {
            // Criar novo usuário
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('123$qweR---'),
                'municipality_id' => $department->municipality_id,
            ]);

            // Atribuir roles baseadas no cargo
            $user->assignRole($beneficiaryRole); // Todos são beneficiários

            // Secretários e Procuradores são validadores
            if (in_array($data['position_symbol'], ['CC-01', 'CC-2A', 'CC-02'])) {
                $user->assignRole($validatorRole);
            }
            
            // Tesoureiros são pagadores
            if ($data['position_symbol'] === 'CC-03') {
                $user->assignRole($payerRole);
            }

            // Vincular ao departamento
            $user->departments()->attach($department->id, ['is_primary' => true]);
        }

        // Criar servidor
        Servant::firstOrCreate(
            ['cpf' => $data['cpf']],
            [
                'user_id' => $user->id,
                'department_id' => $department->id,
                'position_id' => $position->id,
                'name' => $data['name'],
                'rg' => $data['rg'],
                'organ_expeditor' => $data['organ_expeditor'],
                'matricula' => $data['matricula'],
                'bank_name' => $data['bank_name'] ?? 'Banco do Brasil',
                'agency_number' => $data['agency_number'] ?? '1696-9',
                'account_number' => $data['account_number'] ?? $data['matricula'] . '-' . rand(0, 9),
                'account_type' => $data['account_type'] ?? 'corrente',
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'appointment_decree' => $data['decree_number'] ?? null,
                'appointment_date' => $data['decree_date'] ?? null,
            ]
        );

        $this->command->info("✓ Servidor criado: {$data['name']}");
    }
}