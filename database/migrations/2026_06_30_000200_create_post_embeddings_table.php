<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('post_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained('posts')->cascadeOnDelete();
            $table->unsignedSmallInteger('dimensions')->default((int) config('feed.embedding_dimensions', 384));
            $table->string('model')->default((string) config('feed.embedding_model', 'hash-embedding-v1'));
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();
        });

        $dimensions = (int) config('feed.embedding_dimensions', 384);
        DB::statement("ALTER TABLE post_embeddings ADD COLUMN embedding vector({$dimensions}) NOT NULL");
        DB::statement('CREATE INDEX post_embeddings_embedding_hnsw_idx ON post_embeddings USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('post_embeddings');
    }
};
