<?php

declare(strict_types=1);

use App\Models\Municipality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Adiciona slug em municipalities (portal da transparência: URL por slug).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('municipalities', 'slug')) {
            Schema::table('municipalities', function (Blueprint $table): void {
                $table->string('slug', 120)->nullable()->unique()->after('name')->comment('Slug para URL do portal da transparência');
            });
        }

        Municipality::query()
            ->whereNull('slug')
            ->get()
            ->each(function (Municipality $m): void {
                $base = Str::slug($m->name);
                $slug = $base;
                $n = 0;
                while (Municipality::query()->where('slug', $slug)->where('id', '!=', $m->id)->exists()) {
                    $n++;
                    $slug = $base . '-' . $n;
                }
                $m->update(['slug' => $slug]);
            });
    }

    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table): void {
            $table->dropColumn('slug');
        });
    }
};
