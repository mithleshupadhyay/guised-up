<?php

namespace App\Services\Embeddings;

final class VectorFormatter
{
    public static function toPgvector(array $vector): string
    {
        $values = array_map(
            fn (float|int $value): string => number_format((float) $value, 8, '.', ''),
            $vector,
        );

        return '['.implode(',', $values).']';
    }

    public static function fromPgvector(string $value): array
    {
        $trimmed = trim($value, "[] \t\n\r\0\x0B");

        if ($trimmed === '') {
            return [];
        }

        return array_map(
            fn (string $item): float => (float) trim($item),
            explode(',', $trimmed),
        );
    }

    public static function normalize(array $vector): array
    {
        $sum = 0.0;

        foreach ($vector as $value) {
            $sum += ((float) $value) ** 2;
        }

        $norm = sqrt($sum);

        if ($norm <= 0.0) {
            return $vector;
        }

        return array_map(
            fn (float|int $value): float => (float) $value / $norm,
            $vector,
        );
    }

    public static function cosineSimilarity(array $left, array $right): float
    {
        $count = min(count($left), count($right));

        if ($count === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $leftValue = (float) $left[$index];
            $rightValue = (float) $right[$index];
            $dot += $leftValue * $rightValue;
            $leftNorm += $leftValue ** 2;
            $rightNorm += $rightValue ** 2;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }
}
