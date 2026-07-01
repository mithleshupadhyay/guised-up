<?php

return [
    'embedding_dimensions' => (int) env('FEED_EMBEDDING_DIMENSIONS', 384),
    'embedding_model' => env('FEED_EMBEDDING_MODEL', 'hash-embedding-v1'),
    'embedding_service_url' => env('FEED_EMBEDDING_SERVICE_URL', 'http://embedding:8080'),
    'embedding_service_token' => env('FEED_EMBEDDING_SERVICE_TOKEN', ''),
    'embedding_timeout_seconds' => (int) env('FEED_EMBEDDING_TIMEOUT_SECONDS', 3),
    'allow_hash_embedding_fallback' => (bool) env('FEED_ALLOW_HASH_EMBEDDING_FALLBACK', true),

    'feed_per_page' => 20,
    'candidate_days' => 30,
    'relationship_days' => 90,
    'viewer_interest_days' => 60,

    'ranking_weights' => [
        'relationship' => 0.35,
        'authenticity' => 0.30,
        'semantic' => 0.25,
        'time_decay' => 0.10,
    ],
];
