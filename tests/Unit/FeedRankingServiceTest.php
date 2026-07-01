<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\PostEmbedding;
use App\Services\Embeddings\VectorFormatter;
use App\Services\Feed\FeedRankingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FeedRankingServiceTest extends TestCase
{
    public function test_rank_prefers_relationship_and_semantic_relevance_over_newness_alone(): void
    {
        config()->set('feed.ranking_weights', [
            'relationship' => 0.35,
            'authenticity' => 0.30,
            'semantic' => 0.25,
            'time_decay' => 0.10,
        ]);

        $now = CarbonImmutable::parse('2026-06-30 12:00:00');
        $relevant = $this->makePost(
            id: 1,
            authorId: 10,
            authenticity: 0.90,
            createdAt: $now->subHours(8),
            vector: [1.0, 0.0, 0.0],
        );
        $freshButWeak = $this->makePost(
            id: 2,
            authorId: 20,
            authenticity: 0.55,
            createdAt: $now->subMinutes(10),
            vector: [0.0, 1.0, 0.0],
        );

        $ranked = (new FeedRankingService())->rank(
            posts: new Collection([$freshButWeak, $relevant]),
            viewerVector: [1.0, 0.0, 0.0],
            relationshipScores: [10 => 0.85, 20 => 0.0],
            now: $now,
        );

        $this->assertSame(1, $ranked->first()->id);
        $this->assertGreaterThan(
            $ranked->last()->getAttribute('feed_score'),
            $ranked->first()->getAttribute('feed_score'),
        );
    }

    private function makePost(
        int $id,
        int $authorId,
        float $authenticity,
        CarbonImmutable $createdAt,
        array $vector,
    ): Post {
        $post = new Post();
        $post->forceFill([
            'id' => $id,
            'author_id' => $authorId,
            'body' => 'Test post',
            'authenticity_score' => $authenticity,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $embedding = new PostEmbedding();
        $embedding->forceFill([
            'post_id' => $id,
            'embedding' => VectorFormatter::toPgvector($vector),
            'dimensions' => count($vector),
            'model' => 'test',
            'version' => 1,
        ]);

        $post->setRelation('embedding', $embedding);

        return $post;
    }
}
