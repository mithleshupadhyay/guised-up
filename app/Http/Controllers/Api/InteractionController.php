<?php

namespace App\Http\Controllers\Api;

use App\Enums\InteractionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInteractionRequest;
use App\Http\Resources\InteractionResource;
use App\Models\Interaction;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

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

        return (new InteractionResource($interaction))
            ->response()
            ->setStatusCode(201);
    }
}
