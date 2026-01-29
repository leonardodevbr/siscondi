<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->string('name')->comment('Nome da secretaria/setor');
            $table->boolean('is_main')->default(false)->comment('Secretaria principal (ex.: Gabinete do Prefeito)');
            $table->string('cnpj', 18)->nullable()->comment('CNPJ da secretaria');
            $table->string('fund_name')->nullable()->comment('Nome do fundo para pagamento');
            $table->string('fund_code', 50)->nullable()->comment('Código do fundo');
            $table->string('logo_path')->nullable()->comment('Caminho do brasão/logo (storage)');
            $table->timestamps();
        });

        $municipalityId = DB::table('municipalities')->value('id');
        DB::table('departments')->insert([
            'municipality_id' => $municipalityId,
            'name' => 'Gabinete do Prefeito',
            'is_main' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
