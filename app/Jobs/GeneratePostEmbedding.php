<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Embeddings\EmbeddingClient;
use App\Services\Embeddings\VectorFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GeneratePostEmbedding implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int $postId,
    ) {
        $this->onQueue((string) config('feed.embedding_queue', 'embeddings'));
    }

    public function handle(EmbeddingClient $embeddingClient): void
    {
        $post = Post::query()->find($this->postId);

        if ($post === null) {
            Log::warning('[GeneratePostEmbedding] Post no longer exists', [
                'post_id' => $this->postId,
            ]);

            return;
        }

        $embedding = $embeddingClient->embed($post->body);

        $post->embedding()->updateOrCreate(
            ['post_id' => $post->id],
            [
                'embedding' => VectorFormatter::toPgvector($embedding),
                'dimensions' => count($embedding),
                'model' => (string) config('feed.embedding_model', 'hash-embedding-v1'),
                'version' => 1,
            ],
        );

        $metadata = $post->metadata ?? [];
        $metadata['embedding_status'] = 'ready';
        $metadata['embedding_generated_at'] = now()->toJSON();

        $post->forceFill(['metadata' => $metadata])->save();

        Log::info('[GeneratePostEmbedding] Post embedding generated', [
            'post_id' => $post->id,
            'dimensions' => count($embedding),
            'model' => (string) config('feed.embedding_model', 'hash-embedding-v1'),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $post = Post::query()->find($this->postId);

        if ($post !== null) {
            $metadata = $post->metadata ?? [];
            $metadata['embedding_status'] = 'failed';
            $metadata['embedding_failed_at'] = now()->toJSON();

            $post->forceFill(['metadata' => $metadata])->save();
        }

        Log::error('[GeneratePostEmbedding] Post embedding generation failed', [
            'post_id' => $this->postId,
            'message' => $exception->getMessage(),
        ]);
    }
}
