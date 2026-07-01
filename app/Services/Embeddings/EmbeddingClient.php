<?php

namespace App\Services\Embeddings;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class EmbeddingClient
{
    public function __construct(
        private readonly HashEmbeddingGenerator $fallbackGenerator,
    ) {
    }

    public function embed(string $text): array
    {
        $dimensions = (int) config('feed.embedding_dimensions', 384);
        $serviceUrl = rtrim((string) config('feed.embedding_service_url'), '/');

        if ($serviceUrl !== '') {
            try {
                $request = Http::timeout((int) config('feed.embedding_timeout_seconds', 3))
                    ->acceptJson();
                $serviceToken = trim((string) config('feed.embedding_service_token', ''));

                if ($serviceToken !== '') {
                    $request = $request->withHeaders([
                        'X-Embedding-Service-Token' => $serviceToken,
                    ]);
                }

                $response = $request->post($serviceUrl.'/v1/embed', [
                    'texts' => [$text],
                    'dimensions' => $dimensions,
                ]);

                if ($response->successful()) {
                    $vector = $response->json('embeddings.0.vector');

                    if (is_array($vector) && count($vector) === $dimensions) {
                        return VectorFormatter::normalize($vector);
                    }
                }

                Log::warning('[EmbeddingClient] Embedding service returned an invalid response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (Throwable $exception) {
                Log::warning('[EmbeddingClient] Embedding service call failed', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ((bool) config('feed.allow_hash_embedding_fallback', true)) {
            return $this->fallbackGenerator->embed($text, $dimensions);
        }

        throw new RuntimeException('Embedding service failed and hash fallback is disabled.');
    }
}
