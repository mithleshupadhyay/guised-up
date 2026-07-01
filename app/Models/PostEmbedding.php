<?php

namespace App\Models;

use App\Services\Embeddings\VectorFormatter;
use Illuminate\Database\Eloquent\Model;

class PostEmbedding extends Model
{
    protected $fillable = [
        'post_id',
        'embedding',
        'dimensions',
        'model',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'integer',
            'version' => 'integer',
        ];
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function vector(): array
    {
        return VectorFormatter::fromPgvector((string) $this->embedding);
    }
}
