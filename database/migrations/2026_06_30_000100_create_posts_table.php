<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('image_url', 2048)->nullable();
            $table->decimal('image_filter_score', 5, 4)->default(1);
            $table->decimal('text_genuineness_score', 5, 4)->default(1);
            $table->decimal('authenticity_score', 5, 4)->default(1);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['author_id', 'created_at']);
            $table->index(['authenticity_score', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
