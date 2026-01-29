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
            $table->string('code')->unique()->comment('Código do cargo, ex: CC-1');
            $table->string('title')->comment('Nome do cargo, ex: Secretário Municipal');
            $table->string('law_number')->comment('Número da lei que define o cargo');
            $table->decimal('daily_value', 10, 2)->comment('Valor base da diária');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislations');
    }
};
