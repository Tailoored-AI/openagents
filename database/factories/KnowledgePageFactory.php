<?php

namespace Database\Factories;

use App\Models\KnowledgePage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgePage>
 */
class KnowledgePageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'parent_id' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
            'title' => fake()->sentence(3),
            'content' => null,
            'position' => 0,
            'version' => 1,
        ];
    }

    /**
     * Nest the page under the given page, in the same team.
     */
    public function childOf(KnowledgePage $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $parent->team_id,
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Leave the page without a title.
     */
    public function untitled(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => null,
        ]);
    }

    /**
     * Give the page a single paragraph of editor content.
     */
    public function withContent(string $text = 'Hello world'): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'paragraph',
                    'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
                    'content' => [['type' => 'text', 'text' => $text, 'styles' => []]],
                    'children' => [],
                ],
            ],
        ]);
    }
}
