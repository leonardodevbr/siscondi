<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('zone', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->unique(['municipality_id', 'name']);
        });

        Schema::create('manifestations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('cultural_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('artistic_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('cpf', 20)->nullable()->index();
            $table->string('rg', 30)->nullable();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('natural_city')->nullable();
            $table->string('natural_state', 2)->nullable();
            $table->string('education')->nullable();
            $table->string('occupation')->nullable();
            $table->string('other_occupation')->nullable();
            $table->string('years_in_community')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('traditional_knowledge')->nullable();
            $table->boolean('teaches_knowledge')->nullable();
            $table->longText('biography')->nullable();
            $table->boolean('public_profile_enabled')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->index(['municipality_id', 'name']);
        });

        Schema::create('cultural_agent_manifestation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cultural_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manifestation_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cultural_agent_id', 'manifestation_id']);
        });

        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('client_uuid')->unique();
            $table->string('resource');
            $table->string('action');
            $table->string('payload_hash', 64);
            $table->string('status', 30);
            $table->string('result_reference')->nullable();
            $table->json('conflict_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('cultural_agent_manifestation');
        Schema::dropIfExists('cultural_agents');
        Schema::dropIfExists('manifestations');
        Schema::dropIfExists('communities');
    }
};
