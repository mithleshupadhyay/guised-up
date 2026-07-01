<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\Embeddings\EmbeddingClient;
use App\Services\Embeddings\VectorFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    public function __construct(
        private readonly EmbeddingClient $embeddingClient,
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:300'],
        ]);

        $queryVector = VectorFormatter::toPgvector($this->embeddingClient->embed($validated['q']));

        $posts = Post::query()
            ->join('post_embeddings', 'post_embeddings.post_id', '=', 'posts.id')
            ->with('author')
            ->select('posts.*')
            ->selectRaw('1 - (post_embeddings.embedding <=> CAST(? AS vector)) AS semantic_score', [$queryVector])
            ->orderByRaw('post_embeddings.embedding <=> CAST(? AS vector)', [$queryVector])
            ->limit(10)
            ->get();

        Log::info('[SearchController] Semantic search completed', [
            'request_id' => $request->attributes->get('request_id'),
            'viewer_id' => $request->user()?->id,
            'query_length' => strlen($validated['q']),
            'result_count' => $posts->count(),
        ]);

        return PostResource::collection($posts);
    }
}
