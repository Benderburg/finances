<?php

namespace Noros\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noros\Cms\Enums\CommentStatus;
use Noros\Cms\Models\Comment;
use Noros\Cms\Models\Post;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'author_name' => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'body' => fake()->paragraph(),
            'status' => CommentStatus::Approved,
            'approved_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => CommentStatus::Pending,
            'approved_at' => null,
        ]);
    }
}
