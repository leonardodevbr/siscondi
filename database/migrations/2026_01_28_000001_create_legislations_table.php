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
            $table->timestamps();
        });

        Schema::create('legislation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legislation_id')->constrained('legislations')->cascadeOnDelete();
            $table->string('functional_category')->comment('Categoria funcional / cargo a que se aplica');
            $table->string('daily_class')->comment('Classe da diária (ex: Classe A, CC-01)');
            $table->decimal('value_up_to_200km', 10, 2)->default(0)->comment('Cidades até 200km da sede');
            $table->decimal('value_above_200km', 10, 2)->default(0)->comment('Cidades acima de 200km');
            $table->decimal('value_state_capital', 10, 2)->default(0)->comment('Capital do estado');
            $table->decimal('value_other_capitals_df', 10, 2)->default(0)->comment('Demais capitais e DF');
            $table->decimal('value_exterior', 10, 2)->default(0)->comment('Exterior');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislation_items');
        Schema::dropIfExists('legislations');
    }
};
