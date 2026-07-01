<?php

return [
    'embedding_dimensions' => (int) env('FEED_EMBEDDING_DIMENSIONS', 384),
    'embedding_model' => env('FEED_EMBEDDING_MODEL', 'hash-embedding-v1'),
    'embedding_service_url' => env('FEED_EMBEDDING_SERVICE_URL', 'http://embedding:8080'),
    'embedding_service_token' => env('FEED_EMBEDDING_SERVICE_TOKEN', ''),
    'embedding_timeout_seconds' => (int) env('FEED_EMBEDDING_TIMEOUT_SECONDS', 3),
    'allow_hash_embedding_fallback' => (bool) env('FEED_ALLOW_HASH_EMBEDDING_FALLBACK', true),
    'embedding_queue' => env('FEED_EMBEDDING_QUEUE', 'embeddings'),

    'feed_per_page' => 20,
    'candidate_days' => 30,
    'relationship_days' => 90,
    'viewer_interest_days' => 60,
    'cache_profiles' => (bool) env('FEED_CACHE_PROFILES', true),
    'viewer_interest_cache_seconds' => (int) env('FEED_VIEWER_INTEREST_CACHE_SECONDS', 300),
    'relationship_cache_seconds' => (int) env('FEED_RELATIONSHIP_CACHE_SECONDS', 300),

    'request_logging' => (bool) env('FEED_REQUEST_LOGGING', true),

    'rate_limits' => [
        'login_per_minute' => (int) env('FEED_LOGIN_RATE_LIMIT_PER_MINUTE', 10),
        'read_per_minute' => (int) env('FEED_READ_RATE_LIMIT_PER_MINUTE', 120),
        'write_per_minute' => (int) env('FEED_WRITE_RATE_LIMIT_PER_MINUTE', 30),
    ],

    'ranking_weights' => [
        'relationship' => 0.35,
        'authenticity' => 0.30,
        'semantic' => 0.25,
        'time_decay' => 0.10,
    ],
];
