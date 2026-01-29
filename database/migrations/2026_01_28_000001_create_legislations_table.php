<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislations', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->comment('Título da lei / anexo');
            $table->string('law_number')->comment('Número da lei');
            $table->boolean('is_active')->default(true);
            $table->json('destinations')->comment('Lista de destinos desta lei (ex: Até 200 km, Capital Estado)');
            $table->timestamps();
        });

        Schema::create('legislation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legislation_id')->constrained('legislations')->cascadeOnDelete();
            $table->string('functional_category')->comment('Categoria funcional / cargo a que se aplica');
            $table->string('daily_class')->comment('Classe da diária (ex: Classe A, CC-01)');
            $table->json('values')->comment('Valores por destino em centavos: { "Até 200 km": 20000, "Capital Estado": 65000, ... }');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislation_items');
        Schema::dropIfExists('legislations');
    }
};
