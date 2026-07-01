<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\Embeddings\EmbeddingClient;
use App\Services\Embeddings\VectorFormatter;
use App\Services\Feed\AuthenticityScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function __construct(
        private readonly AuthenticityScorer $authenticityScorer,
        private readonly EmbeddingClient $embeddingClient,
    ) {
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $scores = $this->authenticityScorer->score(
            text: $validated['text'],
            imageUrl: $validated['image_url'] ?? null,
        );
        $embedding = $this->embeddingClient->embed($validated['text']);

        $post = DB::transaction(function () use ($request, $validated, $scores, $embedding): Post {
            $post = Post::query()->create([
                'author_id' => $request->user()->id,
                'body' => $validated['text'],
                'image_url' => $validated['image_url'] ?? null,
                'image_filter_score' => $scores['image_filter_score'],
                'text_genuineness_score' => $scores['text_genuineness_score'],
                'authenticity_score' => $scores['authenticity_score'],
                'metadata' => [
                    'authenticity_version' => 1,
                ],
            ]);

            $post->embedding()->create([
                'embedding' => VectorFormatter::toPgvector($embedding),
                'dimensions' => count($embedding),
                'model' => (string) config('feed.embedding_model', 'hash-embedding-v1'),
                'version' => 1,
            ]);

            return $post->load(['author', 'embedding']);
        });

        return (new PostResource($post))
            ->response()
            ->setStatusCode(201);
    }
}
