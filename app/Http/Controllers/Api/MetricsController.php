<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interaction;
use App\Models\Post;
use App\Models\PostEmbedding;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MetricsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'generated_at' => now()->toJSON(),
            'application' => [
                'name' => config('app.name'),
                'environment' => config('app.env'),
            ],
            'feed' => [
                'users' => User::query()->count(),
                'posts' => Post::query()->count(),
                'post_embeddings' => PostEmbedding::query()->count(),
                'posts_waiting_for_embedding' => Post::query()
                    ->whereDoesntHave('embedding')
                    ->count(),
                'interactions' => Interaction::query()->count(),
            ],
            'runtime' => [
                'queue_connection' => config('queue.default'),
                'cache_store' => config('cache.default'),
                'embedding_model' => config('feed.embedding_model'),
                'embedding_dimensions' => config('feed.embedding_dimensions'),
            ],
            'queues' => [
                'pending_jobs' => $this->tableCount('jobs'),
                'failed_jobs' => $this->tableCount('failed_jobs'),
            ],
        ]);
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }
}
