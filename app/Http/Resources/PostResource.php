<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => new UserResource($this->whenLoaded('author')),
            'text' => $this->body,
            'image_url' => $this->image_url,
            'authenticity_score' => $this->authenticity_score,
            'relationship_score' => $this->scoreAttribute('relationship_score'),
            'semantic_score' => $this->scoreAttribute('semantic_score'),
            'time_decay_score' => $this->scoreAttribute('time_decay_score'),
            'feed_score' => $this->scoreAttribute('feed_score'),
            'created_at' => $this->created_at?->toJSON(),
            'time_ago' => $this->created_at?->diffForHumans(),
        ];
    }

    private function scoreAttribute(string $key): mixed
    {
        $value = $this->getAttribute($key);

        return $this->when($value !== null, fn (): float => round((float) $value, 6));
    }
}
