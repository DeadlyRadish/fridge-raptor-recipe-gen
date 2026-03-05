<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecipeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'environment' => app()->environment()
    ]);
});

Route::prefix('v1')->group(function () {
    // Генерация и управление рецептами
    Route::apiResource('recipes', RecipeController::class);
    
    // Альтернативный эндпоинт для генерации
    Route::post('/recipes/generate', [RecipeController::class, 'store']);
    
    // Получение рецептов пользователя
    Route::get('/users/{user_id}/recipes', [RecipeController::class, 'index']);
});