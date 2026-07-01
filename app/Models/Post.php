<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'body',
        'image_url',
        'image_filter_score',
        'text_genuineness_score',
        'authenticity_score',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'image_filter_score' => 'float',
            'text_genuineness_score' => 'float',
            'authenticity_score' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function embedding()
    {
        return $this->hasOne(PostEmbedding::class);
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }
}
