<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servants', function (Blueprint $table): void {
            $table->id();
            
            // Relacionamentos
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete()->comment('Secretaria/Lotação');
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete()->comment('Cargo/Position - OBRIGATÓRIO para definir valores de diária');
            
            // Informações Pessoais
            $table->string('name');
            $table->string('cpf', 11)->unique();
            $table->string('rg', 20);
            $table->string('organ_expeditor', 20)->comment('Órgão expedidor do RG');
            $table->string('matricula')->unique()->comment('Matrícula funcional');
            
            // Informações Bancárias
            $table->string('bank_name')->nullable();
            $table->string('agency_number', 10)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->enum('account_type', ['corrente', 'poupanca'])->nullable();
            
            // Contato
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            
            $table->text('appointment_decree')->nullable();
            $table->date('appointment_date')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servants');
    }
};
