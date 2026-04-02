<?php

namespace App\Http\Controllers;

use App\Models\GeneratedRecipe;
use App\Services\GigaChat\GigaChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RecipeController extends Controller
{
    protected GigaChatService $gigaChatService;

    public function __construct(GigaChatService $gigaChatService)
    {
        $this->gigaChatService = $gigaChatService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userId = $request->query('user_id');

        $query = GeneratedRecipe::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $recipes = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $recipes
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|integer',
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string',
            'products.*.quantity' => 'required|numeric|min:0.1',
            'products.*.unit' => 'required|string',
            'preferences' => 'nullable|array',
            // Поддерживаем числа/булевы, т.к. в промпт потом уходит json_encode.
            'preferences.*' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $ok = is_string($value) || is_numeric($value) || is_bool($value);

                    if (!$ok) {
                        $fail('The :attribute must be a string, number, or boolean.');
                    }
                },
            ],
        ]);

        $userId = $request->input('user_id');
        $products = $request->input('products');
        $preferences = $request->input('preferences', []);

        try {
            // Генерируем рецепт
            $recipeData = $this->gigaChatService->generateRecipe($products, $preferences);

            // Сохраняем в базу
            $recipe = GeneratedRecipe::create([
                'user_id' => $userId,
                'title' => $recipeData['title'],
                'description' => $recipeData['description'],
                'ingredients' => $recipeData['ingredients'],
                'instructions' => $recipeData['instructions'],
                'cooking_time' => $recipeData['cooking_time'],
                'difficulty' => $recipeData['difficulty'],
                'preferences' => $preferences,
                'products_used' => $products,
            ]);

            return response()->json([
                'success' => true,
                'data' => $recipe
            ], 201);

        } catch (\Exception $e) {
            Log::error('Recipe generation error: ' . $e->getMessage(), [
                'products' => $products,
                'preferences' => $preferences,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка генерации рецепта: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $recipe = GeneratedRecipe::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $recipe
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return response()->json([
            'success' => false,
            'error' => 'Метод не поддерживается'
        ], 405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $recipe = GeneratedRecipe::findOrFail($id);
        $recipe->delete();

        return response()->json([
            'success' => true,
            'message' => 'Рецепт удален'
        ]);
    }
}