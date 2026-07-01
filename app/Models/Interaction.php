<?php

namespace App\Models;

use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    protected $fillable = [
        'actor_id',
        'post_id',
        'target_author_id',
        'type',
        'weight',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'weight' => 'float',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function targetAuthor()
    {
        return $this->belongsTo(User::class, 'target_author_id');
    }
}
