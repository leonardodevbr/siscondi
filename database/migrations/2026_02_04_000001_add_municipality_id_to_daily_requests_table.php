<?php

declare(strict_types=1);

use App\Models\DailyRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona municipality_id em daily_requests (multi-tenancy) e preenche a partir de servant->department.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('daily_requests', 'municipality_id')) {
            Schema::table('daily_requests', function (Blueprint $table): void {
                $table->foreignId('municipality_id')->nullable()->after('id')->constrained('municipalities')->nullOnDelete();
            });
        }

        DailyRequest::query()
            ->whereNull('municipality_id')
            ->with('servant.department')
            ->each(function (DailyRequest $req): void {
                $municipalityId = $req->servant?->department?->municipality_id;
                if ($municipalityId !== null) {
                    $req->update(['municipality_id' => $municipalityId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('daily_requests', function (Blueprint $table): void {
            $table->dropForeign(['municipality_id']);
        });
    }
};
