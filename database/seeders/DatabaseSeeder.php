<?php

namespace Database\Seeders;

use App\Enums\InteractionType;
use App\Models\Interaction;
use App\Models\Post;
use App\Models\User;
use App\Services\Embeddings\HashEmbeddingGenerator;
use App\Services\Embeddings\VectorFormatter;
use App\Services\Feed\AuthenticityScorer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = collect([
            ['name' => 'Mithlesh Upadhyay', 'username' => 'mithlesh', 'email' => 'mithlesh@example.com'],
            ['name' => 'Prachi Debnath', 'username' => 'prachi', 'email' => 'prachi@example.com'],
            ['name' => 'Demo User', 'username' => 'demo', 'email' => 'demo@example.com'],
        ])->map(fn (array $user): User => User::query()->updateOrCreate(
            ['email' => $user['email']],
            [
                'name' => $user['name'],
                'username' => $user['username'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        ));

        $scorer = app(AuthenticityScorer::class);
        $embedder = app(HashEmbeddingGenerator::class);
        $samplePosts = [
            [
                'author' => 'prachi@example.com',
                'body' => 'Took the wrong metro today and ended up finding a quiet bookshop. Small accidental joy.',
                'image_url' => null,
            ],
            [
                'author' => 'prachi@example.com',
                'body' => 'Trying to document more ordinary days. Today was just rain, chai, and a half-finished idea.',
                'image_url' => 'https://example.com/raw-rain-walk.jpg',
            ],
            [
                'author' => 'demo@example.com',
                'body' => 'Funny travel story: I packed everything except socks and had to bargain in a hill-station market.',
                'image_url' => null,
            ],
            [
                'author' => 'demo@example.com',
                'body' => 'LIMITED OFFER follow for more perfect travel edits!!! #wanderlust #goals #viral',
                'image_url' => 'https://example.com/beauty-filter-studio-shot.jpg',
            ],
        ];

        foreach ($samplePosts as $samplePost) {
            $author = $users->firstWhere('email', $samplePost['author']);
            $scores = $scorer->score($samplePost['body'], $samplePost['image_url']);

            $post = Post::query()->firstOrCreate(
                [
                    'author_id' => $author->id,
                    'body' => $samplePost['body'],
                ],
                [
                    'image_url' => $samplePost['image_url'],
                    'image_filter_score' => $scores['image_filter_score'],
                    'text_genuineness_score' => $scores['text_genuineness_score'],
                    'authenticity_score' => $scores['authenticity_score'],
                    'metadata' => ['seeded' => true],
                ],
            );

            if (! $post->embedding()->exists()) {
                $vector = $embedder->embed($post->body, (int) config('feed.embedding_dimensions', 384));
                $post->embedding()->create([
                    'embedding' => VectorFormatter::toPgvector($vector),
                    'dimensions' => count($vector),
                    'model' => (string) config('feed.embedding_model', 'hash-embedding-v1'),
                    'version' => 1,
                ]);
            }
        }

        $mithlesh = $users->firstWhere('email', 'mithlesh@example.com');
        $travelPost = Post::query()
            ->where('body', 'like', '%Funny travel story%')
            ->first();

        if ($mithlesh && $travelPost && ! Interaction::query()->where('actor_id', $mithlesh->id)->exists()) {
            foreach ([InteractionType::View, InteractionType::Reaction, InteractionType::Reply] as $type) {
                Interaction::query()->create([
                    'actor_id' => $mithlesh->id,
                    'post_id' => $travelPost->id,
                    'target_author_id' => $travelPost->author_id,
                    'type' => $type,
                    'weight' => $type->weight(),
                    'metadata' => ['seeded' => true],
                ]);
            }
        }
    }
}
