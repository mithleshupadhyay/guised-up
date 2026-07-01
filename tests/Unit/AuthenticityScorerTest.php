<?php

namespace Tests\Unit;

use App\Services\Feed\AuthenticityScorer;
use PHPUnit\Framework\TestCase;

class AuthenticityScorerTest extends TestCase
{
    public function test_personal_unfiltered_text_scores_higher_than_promotional_copy(): void
    {
        $scorer = new AuthenticityScorer();

        $personal = $scorer->score(
            'I got lost today and honestly it became the best part of my walk.',
            'https://example.com/raw-walk.jpg',
        );
        $promotional = $scorer->score(
            'LIMITED OFFER follow for more perfect edits!!! #viral #goals #wanderlust',
            'https://example.com/beauty-filter-studio.jpg',
        );

        $this->assertGreaterThan($promotional['authenticity_score'], $personal['authenticity_score']);
        $this->assertGreaterThan(0.70, $personal['authenticity_score']);
        $this->assertLessThan(0.70, $promotional['authenticity_score']);
    }
}
