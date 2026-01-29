<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained('municipalities')->cascadeOnDelete();
            $table->string('symbol')->comment('Símbolo do cargo (ex.: 101, 201)');
            $table->string('name')->nullable()->comment('Nome/descrição do cargo');
            $table->string('role', 50)->nullable()->comment('Perfil Spatie vinculado a este cargo (admin, requester, validator, etc.)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};
