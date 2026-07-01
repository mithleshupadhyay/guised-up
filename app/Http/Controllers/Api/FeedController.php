<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Interaction;
use App\Models\Post;
use App\Services\Feed\FeedRankingService;
use App\Services\Feed\ViewerInterestProfile;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function __construct(
        private readonly FeedRankingService $rankingService,
        private readonly ViewerInterestProfile $viewerInterestProfile,
    ) {
    }

    public function index(Request $request)
    {
        $viewer = $request->user();
        $now = CarbonImmutable::now();
        $perPage = (int) config('feed.feed_per_page', 20);
        $page = max(1, (int) $request->query('page', 1));

        $viewerVector = $this->viewerInterestProfile->build($viewer, $now);
        $relationshipScores = $this->relationshipScores($viewer->id, $now);

        $candidates = Post::query()
            ->with(['author', 'embedding'])
            ->where('author_id', '<>', $viewer->id)
            ->where('created_at', '>=', $now->subDays((int) config('feed.candidate_days', 30)))
            ->latest()
            ->limit(500)
            ->get();

        $ranked = $this->rankingService->rank($candidates, $viewerVector, $relationshipScores, $now);
        $items = $ranked->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            items: $items,
            total: $ranked->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return PostResource::collection($paginator);
    }

    private function relationshipScores(int $viewerId, CarbonImmutable $now): array
    {
        $rows = Interaction::query()
            ->select('target_author_id', DB::raw('SUM(weight) AS score'))
            ->where('actor_id', $viewerId)
            ->where('created_at', '>=', $now->subDays((int) config('feed.relationship_days', 90)))
            ->groupBy('target_author_id')
            ->get();

        $maxScore = max(1.0, (float) $rows->max('score'));

        return $rows
            ->mapWithKeys(fn ($row): array => [
                (int) $row->target_author_id => min(1.0, log(1.0 + (float) $row->score) / log(1.0 + $maxScore)),
            ])
            ->all();
    }
}
