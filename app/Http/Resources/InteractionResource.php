<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InteractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'type' => $this->type->value,
            'weight' => $this->weight,
            'created_at' => $this->created_at?->toJSON(),
        ];
    }
}
