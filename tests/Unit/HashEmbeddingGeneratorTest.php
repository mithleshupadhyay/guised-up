<?php

namespace Tests\Unit;

use App\Services\Embeddings\HashEmbeddingGenerator;
use App\Services\Embeddings\VectorFormatter;
use PHPUnit\Framework\TestCase;

class HashEmbeddingGeneratorTest extends TestCase
{
    public function test_hash_embeddings_are_deterministic_and_normalized(): void
    {
        $generator = new HashEmbeddingGenerator();

        $first = $generator->embed('funny travel story from last week', 32);
        $second = $generator->embed('funny travel story from last week', 32);

        $this->assertSame($first, $second);
        $this->assertCount(32, $first);
        $this->assertEqualsWithDelta(1.0, sqrt(array_sum(array_map(
            fn (float $value): float => $value ** 2,
            $first,
        ))), 0.000001);
    }

    public function test_pgvector_round_trip_keeps_values(): void
    {
        $vector = [0.25, -0.5, 0.75];
        $encoded = VectorFormatter::toPgvector($vector);

        $this->assertSame('[0.25000000,-0.50000000,0.75000000]', $encoded);
        $this->assertSame($vector, VectorFormatter::fromPgvector($encoded));
    }
}
