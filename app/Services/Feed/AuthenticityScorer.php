<?php

namespace App\Services\Feed;

final class AuthenticityScorer
{
    public function score(string $text, ?string $imageUrl): array
    {
        $textScore = $this->scoreText($text);
        $imageScore = $this->scoreImageUrl($imageUrl);
        $combinedScore = (0.70 * $textScore) + (0.30 * $imageScore);

        return [
            'text_genuineness_score' => round($textScore, 4),
            'image_filter_score' => round($imageScore, 4),
            'authenticity_score' => round($this->clamp($combinedScore), 4),
        ];
    }

    private function scoreText(string $text): float
    {
        $lower = strtolower($text);
        $score = 0.68;

        $personalMarkers = ['i ', 'me ', 'my ', 'today', 'felt', 'learned', 'honest', 'real', 'messy', 'unfiltered'];
        foreach ($personalMarkers as $marker) {
            if (str_contains($lower, $marker)) {
                $score += 0.035;
            }
        }

        $polishedMarkers = ['limited offer', 'follow for more', 'link in bio', 'sponsored', 'giveaway', 'dm for collab'];
        foreach ($polishedMarkers as $marker) {
            if (str_contains($lower, $marker)) {
                $score -= 0.08;
            }
        }

        $hashtagCount = substr_count($text, '#');
        $mentionCount = substr_count($text, '@');
        $exclamationCount = substr_count($text, '!');

        $score -= min(0.20, $hashtagCount * 0.025);
        $score -= min(0.10, $mentionCount * 0.015);
        $score -= min(0.10, max(0, $exclamationCount - 2) * 0.02);

        if (strlen(trim($text)) >= 40 && strlen(trim($text)) <= 280) {
            $score += 0.06;
        }

        return $this->clamp($score);
    }

    private function scoreImageUrl(?string $imageUrl): float
    {
        if ($imageUrl === null || trim($imageUrl) === '') {
            return 1.0;
        }

        $lower = strtolower($imageUrl);
        $score = 0.76;
        $polishedMarkers = ['filter', 'beauty', 'retouch', 'studio', 'stock', 'preset', 'edited'];

        foreach ($polishedMarkers as $marker) {
            if (str_contains($lower, $marker)) {
                $score -= 0.10;
            }
        }

        if (str_contains($lower, 'raw') || str_contains($lower, 'unfiltered')) {
            $score += 0.12;
        }

        return $this->clamp($score);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
