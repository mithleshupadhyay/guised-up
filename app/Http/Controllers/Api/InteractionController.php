<?php

namespace App\Http\Controllers\Api;

use App\Enums\InteractionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInteractionRequest;
use App\Http\Resources\InteractionResource;
use App\Models\Interaction;
use App\Models\Post;
use App\Services\Feed\FeedCacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InteractionController extends Controller
{
    public function store(StoreInteractionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $post = Post::query()->findOrFail($validated['post_id']);
        $type = InteractionType::from($validated['type']);

        $interaction = Interaction::query()->create([
            'actor_id' => $request->user()->id,
            'post_id' => $post->id,
            'target_author_id' => $post->author_id,
            'type' => $type,
            'weight' => $type->weight(),
            'metadata' => $validated['metadata'] ?? null,
        ]);

        FeedCacheKeys::forgetViewer($request->user()->id);

        Log::info('[InteractionController] Interaction recorded', [
            'request_id' => $request->attributes->get('request_id'),
            'interaction_id' => $interaction->id,
            'actor_id' => $request->user()->id,
            'post_id' => $post->id,
            'target_author_id' => $post->author_id,
            'type' => $type->value,
        ]);

        return (new InteractionResource($interaction))
            ->response()
            ->setStatusCode(201);
    }
}
