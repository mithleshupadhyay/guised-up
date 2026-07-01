<?php

return [
    'embedding' => [
        'url' => env('FEED_EMBEDDING_SERVICE_URL', 'http://embedding:8080'),
        'timeout' => (int) env('FEED_EMBEDDING_TIMEOUT_SECONDS', 3),
    ],
];
