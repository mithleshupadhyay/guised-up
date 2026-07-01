<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('target_author_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24);
            $table->decimal('weight', 5, 2)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['actor_id', 'target_author_id', 'created_at']);
            $table->index(['post_id', 'type', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
