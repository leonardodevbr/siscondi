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
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->comment('Nome da secretaria/setor');
            $table->boolean('is_main')->default(false)->comment('Secretaria principal (ex.: Gabinete do Prefeito)');
            $table->timestamps();
        });

        DB::table('departments')->insert([
            'name' => 'Gabinete do Prefeito',
            'is_main' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
