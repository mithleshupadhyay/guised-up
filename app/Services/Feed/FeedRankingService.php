<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Services\Embeddings\VectorFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class FeedRankingService
{
    public function rank(Collection $posts, array $viewerVector, array $relationshipScores, CarbonImmutable $now): Collection
    {
        $weights = config('feed.ranking_weights');

        return $posts
            ->map(function (Post $post) use ($viewerVector, $relationshipScores, $now, $weights): Post {
                $postVector = $post->embedding?->vector() ?? [];
                $semanticScore = count($viewerVector) > 0 && count($postVector) > 0
                    ? max(0.0, VectorFormatter::cosineSimilarity($viewerVector, $postVector))
                    : 0.50;

                $relationshipScore = (float) ($relationshipScores[$post->author_id] ?? 0.0);
                $ageHours = max(0.0, $post->created_at->diffInHours($now));
                $timeDecayScore = exp(-$ageHours / 72);
                $authenticityScore = (float) $post->authenticity_score;

                $feedScore = (
                    ((float) $weights['relationship'] * $relationshipScore) +
                    ((float) $weights['authenticity'] * $authenticityScore) +
                    ((float) $weights['semantic'] * $semanticScore) +
                    ((float) $weights['time_decay'] * $timeDecayScore)
                );

                $post->setAttribute('relationship_score', round($relationshipScore, 6));
                $post->setAttribute('semantic_score', round($semanticScore, 6));
                $post->setAttribute('time_decay_score', round($timeDecayScore, 6));
                $post->setAttribute('feed_score', round($feedScore, 6));

                return $post;
            })
            ->sortByDesc(fn (Post $post): string => sprintf(
                '%012.6f-%020d',
                (float) $post->getAttribute('feed_score'),
                $post->created_at?->getTimestamp() ?? 0,
            ))
            ->values();
    }
}
