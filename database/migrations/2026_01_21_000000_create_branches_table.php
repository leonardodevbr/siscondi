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
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_main')->default(false);
            $table->timestamps();
        });

        // SISCONDI: Gabinete do Prefeito como secretaria principal
        DB::table('branches')->insert([
            'name' => 'Gabinete do Prefeito',
            'is_main' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
