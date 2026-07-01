<?php

namespace App\Services\Feed;

use App\Models\Interaction;
use App\Models\User;
use App\Services\Embeddings\VectorFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class ViewerInterestProfile
{
    public function build(User $viewer, CarbonImmutable $now): array
    {
        if (! (bool) config('feed.cache_profiles', true)) {
            return $this->buildFresh($viewer, $now);
        }

        return Cache::remember(
            FeedCacheKeys::viewerInterest($viewer->id),
            (int) config('feed.viewer_interest_cache_seconds', 300),
            fn (): array => $this->buildFresh($viewer, $now),
        );
    }

    private function buildFresh(User $viewer, CarbonImmutable $now): array
    {
        $interactions = Interaction::query()
            ->where('actor_id', $viewer->id)
            ->where('created_at', '>=', $now->subDays((int) config('feed.viewer_interest_days', 60)))
            ->with('post.embedding')
            ->latest()
            ->limit(150)
            ->get();

        $weighted = [];
        $totalWeight = 0.0;

        foreach ($interactions as $interaction) {
            $vector = $interaction->post?->embedding?->vector();

            if ($vector === null || count($vector) === 0) {
                continue;
            }

            $ageHours = max(0.0, $interaction->created_at->diffInHours($now));
            $recencyDecay = exp(-$ageHours / 336);
            $weight = ((float) $interaction->weight) * $recencyDecay;

            foreach ($vector as $index => $value) {
                $weighted[$index] = ($weighted[$index] ?? 0.0) + (((float) $value) * $weight);
            }

            $totalWeight += $weight;
        }

        if ($totalWeight <= 0.0) {
            return [];
        }

        ksort($weighted);

        $average = array_map(
            fn (float $value): float => $value / $totalWeight,
            array_values($weighted),
        );

        return VectorFormatter::normalize($average);
    }
}
