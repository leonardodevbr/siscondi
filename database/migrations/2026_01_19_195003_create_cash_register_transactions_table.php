<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_register_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_register_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->foreignId('sale_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_register_transactions');
    }
};
