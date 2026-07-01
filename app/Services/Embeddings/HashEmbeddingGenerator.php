<?php

namespace App\Services\Embeddings;

final class HashEmbeddingGenerator
{
    public function embed(string $text, int $dimensions): array
    {
        $dimensions = max(8, $dimensions);
        $tokens = preg_split('/[^a-z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        if ($tokens === false || count($tokens) === 0) {
            $tokens = ['empty'];
        }

        $vector = array_fill(0, $dimensions, 0.0);

        foreach ($tokens as $position => $token) {
            $hash = crc32($token);
            $index = $hash % $dimensions;
            $sign = ($hash & 1) === 1 ? 1.0 : -1.0;
            $lengthBoost = 1.0 + min(strlen($token), 16) / 16;
            $positionDecay = 1.0 / sqrt($position + 1);

            $vector[$index] += $sign * $lengthBoost * $positionDecay;
        }

        return VectorFormatter::normalize($vector);
    }
}
