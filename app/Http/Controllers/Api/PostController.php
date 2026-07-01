<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Jobs\GeneratePostEmbedding;
use App\Models\Post;
use App\Services\Feed\AuthenticityScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    public function __construct(
        private readonly AuthenticityScorer $authenticityScorer,
    ) {
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $scores = $this->authenticityScorer->score(
            text: $validated['text'],
            imageUrl: $validated['image_url'] ?? null,
        );

        $post = DB::transaction(function () use ($request, $validated, $scores): Post {
            $post = Post::query()->create([
                'author_id' => $request->user()->id,
                'body' => $validated['text'],
                'image_url' => $validated['image_url'] ?? null,
                'image_filter_score' => $scores['image_filter_score'],
                'text_genuineness_score' => $scores['text_genuineness_score'],
                'authenticity_score' => $scores['authenticity_score'],
                'metadata' => [
                    'authenticity_version' => 1,
                    'embedding_status' => 'queued',
                ],
            ]);

            return $post;
        });

        GeneratePostEmbedding::dispatch($post->id);
        $post->load(['author', 'embedding']);

        Log::info('[PostController] Post created', [
            'request_id' => $request->attributes->get('request_id'),
            'post_id' => $post->id,
            'author_id' => $request->user()->id,
            'authenticity_score' => $scores['authenticity_score'],
        ]);

        return (new PostResource($post))
            ->response()
            ->setStatusCode(201);
    }
}
