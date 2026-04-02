<?php

namespace Tests\Feature;

use App\Models\GeneratedRecipe;
use App\Services\GigaChat\GigaChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeGenerateEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_recipe_endpoint_returns_201_and_persists_recipe(): void
    {
        $this->mock(GigaChatService::class, function ($mock) {
            $mock->shouldReceive('generateRecipe')
                ->once()
                ->andReturn([
                    'title' => 'Тестовый рецепт',
                    'description' => 'Описание',
                    'ingredients' => [['name' => 'Хлеб', 'quantity' => 1, 'unit' => 'шт']],
                    'instructions' => [['step' => 1, 'description' => 'Сделать тест']],
                    'cooking_time' => 30,
                    'difficulty' => 'easy',
                    'tips' => ['Совет'],
                ]);
        });

        $payload = [
            'user_id' => 1,
            'products' => [
                ['name' => 'Хлеб', 'quantity' => 1, 'unit' => 'шт'],
            ],
            'preferences' => [
                'diet' => 'none',
                'max_time' => 45,
            ],
        ];

        $response = $this->postJson(route('recipes.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Тестовый рецепт')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_id',
                    'title',
                    'description',
                    'ingredients',
                    'instructions',
                    'cooking_time',
                    'difficulty',
                    'preferences',
                    'products_used',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('generated_recipes', [
            'title' => 'Тестовый рецепт',
        ]);

        $this->assertSame('Тестовый рецепт', GeneratedRecipe::query()->latest('id')->first()->title);
    }

    public function test_generate_recipe_endpoint_allows_numeric_preferences_values(): void
    {
        $this->mock(GigaChatService::class, function ($mock) {
            $mock->shouldReceive('generateRecipe')
                ->once()
                ->andReturn([
                    'title' => 'Тестовый рецепт',
                    'description' => 'Описание',
                    'ingredients' => [],
                    'instructions' => [],
                    'cooking_time' => 30,
                    'difficulty' => 'easy',
                    'tips' => [],
                ]);
        });

        $payload = [
            'user_id' => 1,
            'products' => [
                ['name' => 'Хлеб', 'quantity' => 1, 'unit' => 'шт'],
            ],
            'preferences' => [
                'max_time' => 45,
                'vegetarian' => false,
            ],
        ];

        $this->postJson(route('recipes.store'), $payload)
            ->assertStatus(201);
    }

    public function test_healthcheck_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status',
                'timestamp',
                'environment',
            ]);
    }

    public function test_generate_recipe_endpoint_returns_422_when_products_missing(): void
    {
        $payload = [
            'user_id' => 1,
            'preferences' => [
                'diet' => 'none',
            ],
        ];

        $response = $this->postJson(route('recipes.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products']);
    }

    public function test_generate_recipe_endpoint_returns_500_when_gigachat_throws_exception(): void
    {
        $this->mock(GigaChatService::class, function ($mock) {
            $mock->shouldReceive('generateRecipe')
                ->once()
                ->andThrow(new \RuntimeException('GigaChat down'));
        });

        $payload = [
            'user_id' => 1,
            'products' => [
                ['name' => 'Хлеб', 'quantity' => 1, 'unit' => 'шт'],
            ],
            'preferences' => [],
        ];

        $response = $this->postJson(route('recipes.store'), $payload);

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'error' => 'Ошибка генерации рецепта: GigaChat down',
            ]);

        $this->assertDatabaseCount('generated_recipes', 0);
    }

    public function test_generate_recipe_endpoint_allows_missing_user_id_and_preferences(): void
    {
        $this->mock(GigaChatService::class, function ($mock) {
            $mock->shouldReceive('generateRecipe')
                ->once()
                ->withArgs(function (array $products, array $preferences) {
                    return count($products) === 1
                        && $products[0]['name'] === 'Хлеб'
                        && $preferences === [];
                })
                ->andReturn([
                    'title' => 'Тестовый рецепт',
                    'description' => 'Описание',
                    'ingredients' => [],
                    'instructions' => [],
                    'cooking_time' => 30,
                    'difficulty' => 'easy',
                ]);
        });

        $payload = [
            'products' => [
                ['name' => 'Хлеб', 'quantity' => 1, 'unit' => 'шт'],
            ],
        ];

        $response = $this->postJson(route('recipes.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Тестовый рецепт')
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.preferences', []);

        $this->assertDatabaseHas('generated_recipes', [
            'title' => 'Тестовый рецепт',
            'user_id' => null,
        ]);
    }
}

