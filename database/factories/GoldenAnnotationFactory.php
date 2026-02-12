<?php

namespace Aicl\Database\Factories;

use Aicl\Enums\AnnotationCategory;
use Aicl\Models\GoldenAnnotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoldenAnnotation>
 */
class GoldenAnnotationFactory extends Factory
{
    protected $model = GoldenAnnotation::class;

    public function definition(): array
    {
        $category = fake()->randomElement(AnnotationCategory::cases());
        $file = $category->value.'.php';

        return [
            'annotation_key' => $category->value.'.'.fake()->unique()->slug(2),
            'file_path' => $file,
            'line_number' => fake()->numberBetween(1, 200),
            'annotation_text' => fake()->sentence(),
            'rationale' => fake()->optional(0.5)->paragraph(),
            'feature_tags' => fake()->randomElements(
                ['universal', 'states', 'media', 'enum', 'pdf', 'notifications', 'tagging', 'search', 'audit', 'api', 'widgets'],
                fake()->numberBetween(1, 3),
            ),
            'pattern_name' => fake()->optional(0.6)->slug(2),
            'category' => $category,
            'is_active' => true,
            'owner_id' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function universal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'feature_tags' => ['universal'],
        ]);
    }

    public function forCategory(AnnotationCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category,
            'file_path' => $category->value.'.php',
        ]);
    }

    public function withFeatureTags(array $tags): static
    {
        return $this->state(fn (array $attributes): array => [
            'feature_tags' => $tags,
        ]);
    }

    public function forPattern(string $patternName): static
    {
        return $this->state(fn (array $attributes): array => [
            'pattern_name' => $patternName,
        ]);
    }
}
