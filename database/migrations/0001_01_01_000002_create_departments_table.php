<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name')->comment('Nome da secretaria/setor');
            $table->string('code')->nullable()->comment('Código da secretaria/setor');
            $table->text('description')->nullable()->comment('Código da secretaria/setor');
            $table->integer('total_employees')->nullable()->comment('Total de cargos da secretaria/setor');
            $table->boolean('is_main')->default(false)->comment('Secretaria principal (ex.: Gabinete do Prefeito)');
            $table->string('fund_cnpj', 18)->nullable()->comment('CNPJ do fundo para pagamento');
            $table->string('fund_name')->nullable()->comment('Nome do fundo para pagamento');
            $table->string('fund_code', 50)->nullable()->comment('Código do fundo');
            $table->string('logo_path')->nullable()->comment('Caminho do brasão/logo (storage)');
            
            // Endereço
            $table->string('address')->nullable()->comment('Endereço completo');
            $table->string('neighborhood')->nullable()->comment('Bairro');
            $table->string('zip_code', 10)->nullable()->comment('CEP');
            $table->string('phone', 20)->nullable()->comment('Telefone');
            $table->string('email')->nullable()->comment('E-mail');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
