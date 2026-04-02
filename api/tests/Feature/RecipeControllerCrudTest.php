<?php

namespace Tests\Feature;

use App\Models\GeneratedRecipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeControllerCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createRecipe(int $userId, string $title): GeneratedRecipe
    {
        return GeneratedRecipe::create([
            'user_id' => $userId,
            'title' => $title,
            'description' => 'Описание',
            'ingredients' => [['name' => 'Хлеб', 'quantity' => 1, 'unit' => 'шт']],
            'instructions' => [['step' => 1, 'description' => 'Сделать тест']],
            'cooking_time' => '30',
            'difficulty' => 'easy',
            'preferences' => ['diet' => 'none'],
            'products_used' => [['name' => 'Хлеб', 'quantity' => 1, 'unit' => 'шт']],
        ]);
    }

    public function test_index_filters_by_user_id_query_param(): void
    {
        $this->createRecipe(1, 'Р1');
        $this->createRecipe(2, 'Р2');

        $response = $this->getJson('/api/v1/recipes?user_id=1');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = $response->json('data.data');

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertSame(1, $item['user_id']);
        }
    }

    public function test_show_returns_recipe(): void
    {
        $recipe = $this->createRecipe(1, 'Показать');

        $response = $this->getJson("/api/v1/recipes/{$recipe->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $recipe->id)
            ->assertJsonPath('data.title', 'Показать');
    }

    public function test_show_returns_404_for_missing_recipe(): void
    {
        $response = $this->getJson('/api/v1/recipes/999999');

        $response->assertStatus(404);
    }

    public function test_destroy_deletes_recipe(): void
    {
        $recipe = $this->createRecipe(1, 'Удалить');

        $response = $this->deleteJson("/api/v1/recipes/{$recipe->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'message' => 'Рецепт удален',
            ]);

        $this->assertDatabaseMissing('generated_recipes', [
            'id' => $recipe->id,
        ]);
    }

    public function test_update_returns_405(): void
    {
        $recipe = $this->createRecipe(1, 'Обновление');

        $response = $this->putJson("/api/v1/recipes/{$recipe->id}", []);

        $response->assertStatus(405)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Метод не поддерживается');
    }
}

