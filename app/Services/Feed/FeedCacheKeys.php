<?php

namespace App\Services\Feed;

use Illuminate\Support\Facades\Cache;

final class FeedCacheKeys
{
    public static function viewerInterest(int $viewerId): string
    {
        return "feed:v1:viewer:{$viewerId}:interest";
    }

    public static function relationshipScores(int $viewerId): string
    {
        return "feed:v1:viewer:{$viewerId}:relationships";
    }

    public static function forgetViewer(int $viewerId): void
    {
        Cache::forget(self::viewerInterest($viewerId));
        Cache::forget(self::relationshipScores($viewerId));
    }
}
