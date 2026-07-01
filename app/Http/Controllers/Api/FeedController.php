<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\Feed\FeedRankingService;
use App\Services\Feed\RelationshipScoreService;
use App\Services\Feed\ViewerInterestProfile;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class FeedController extends Controller
{
    public function __construct(
        private readonly FeedRankingService $rankingService,
        private readonly RelationshipScoreService $relationshipScoreService,
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
        $relationshipScores = $this->relationshipScoreService->forViewer($viewer->id, $now);

        $candidates = Post::query()
            ->with(['author', 'embedding'])
            ->where('author_id', '<>', $viewer->id)
            ->where('created_at', '>=', $now->subDays((int) config('feed.candidate_days', 30)))
            ->latest()
            ->limit(500)
            ->get();

        $ranked = $this->rankingService->rank($candidates, $viewerVector, $relationshipScores, $now);
        $items = $ranked->slice(($page - 1) * $perPage, $perPage)->values();

        Log::info('[FeedController] Ranked feed request', [
            'request_id' => $request->attributes->get('request_id'),
            'viewer_id' => $viewer->id,
            'candidate_count' => $candidates->count(),
            'ranked_count' => $ranked->count(),
            'page' => $page,
        ]);

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
}
