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
            $table->string('cnpj', 18)->nullable()->comment('CNPJ da prefeitura');
            $table->string('state', 2)->nullable()->comment('UF');
            $table->string('address')->nullable()->comment('Endereço');
            $table->string('email')->nullable()->comment('E-mail');
            $table->string('logo_path')->nullable()->comment('Caminho do brasão (storage)');
            $table->timestamps();
        });

        DB::table('municipalities')->insert([
            'name' => 'Prefeitura Municipal de Cafarnaum',
            'cnpj' => '17.622.151/0001-84',
            'state' => 'BA',
            'address' => 'Avenida João Costa Brasil, 315 - Centro - Cafarnaum - BA',
            'email' => 'social@cafarnaum.ba.gov.br',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
