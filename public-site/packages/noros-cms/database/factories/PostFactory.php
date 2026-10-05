<?php

namespace Noros\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Noros\Cms\Enums\PostStatus;
use Noros\Cms\Models\Category;
use Noros\Cms\Models\Post;
use Noros\Core\Models\User;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'author_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'excerpt' => fake()->paragraph(),
            'content' => '<p>'.fake()->paragraphs(5, true).'</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
            'allow_comments' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }
}
