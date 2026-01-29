<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mantido apenas para compatibilidade com histórico de migrations.
     * Com migrate:fresh, legislations/legislation_items já nascem com destinations/values (JSON)
     * e daily_requests já nascem com unit_value/total_value em centavos (integer).
     * Nada a fazer.
     */
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
