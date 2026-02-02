<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislation_item_position', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('legislation_item_id')->constrained('legislation_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['position_id', 'legislation_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislation_item_position');
    }
};
