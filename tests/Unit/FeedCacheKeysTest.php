<?php

namespace Tests\Unit;

use App\Services\Feed\FeedCacheKeys;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FeedCacheKeysTest extends TestCase
{
    public function test_forget_viewer_removes_cached_feed_profiles(): void
    {
        Cache::put(FeedCacheKeys::viewerInterest(7), [0.1, 0.2], 60);
        Cache::put(FeedCacheKeys::relationshipScores(7), [2 => 0.8], 60);

        FeedCacheKeys::forgetViewer(7);

        $this->assertFalse(Cache::has(FeedCacheKeys::viewerInterest(7)));
        $this->assertFalse(Cache::has(FeedCacheKeys::relationshipScores(7)));
    }
}
