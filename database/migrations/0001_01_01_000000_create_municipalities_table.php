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
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->comment('Nome do município');
            $table->string('display_name')->comment('Nome completo para exibir em documentos');
            $table->string('cnpj', 18)->nullable()->comment('CNPJ da prefeitura');
            $table->string('state', 2)->nullable()->comment('UF');
            $table->string('display_state', 100)->nullable()->comment('Nome de exibição: Ex: Estado da Bahia');
            $table->string('logo_path')->nullable()->comment('Caminho do brasão (storage)');
            $table->timestamps();
        });

        DB::table('municipalities')->insert([
            'name' => 'Cafarnaum',
            'display_name' => 'Prefeitura Municipal de Cafarnaum',
            'cnpj' => '17.622.151/0001-84',
            'state' => 'BA',
            'display_state' => 'Estado da Bahia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
