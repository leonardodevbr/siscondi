<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servant_cargo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('servant_id')->constrained('servants')->cascadeOnDelete();
            $table->foreignId('cargo_id')->constrained('cargos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['servant_id', 'cargo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servant_cargo');
    }
};
