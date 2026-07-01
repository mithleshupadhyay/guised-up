<?php

namespace App\Services\Feed;

use App\Models\Interaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RelationshipScoreService
{
    public function forViewer(int $viewerId, CarbonImmutable $now): array
    {
        if (! (bool) config('feed.cache_profiles', true)) {
            return $this->build($viewerId, $now);
        }

        return Cache::remember(
            FeedCacheKeys::relationshipScores($viewerId),
            (int) config('feed.relationship_cache_seconds', 300),
            fn (): array => $this->build($viewerId, $now),
        );
    }

    private function build(int $viewerId, CarbonImmutable $now): array
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
