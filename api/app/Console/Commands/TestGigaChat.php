<?php

namespace App\Console\Commands;

use App\Services\GigaChat\GigaChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestGigaChat extends Command
{
    protected $signature = 'gigachat:test 
                            {--products= : JSON с продуктами}
                            {--prefs= : JSON с пожеланиями}';
    
    protected $description = 'Тестирование подключения к GigaChat API';

    public function handle(GigaChatService $gigaChat): int
    {
        $this->info('🔌 Тестирование GigaChat API...');

        // Тестовые данные, если не переданы
        $products = json_decode($this->option('products'), true) ?? [
            ['name' => 'Куриное филе', 'quantity' => 300, 'unit' => 'г'],
            ['name' => 'Картофель', 'quantity' => 4, 'unit' => 'шт'],
            ['name' => 'Морковь', 'quantity' => 1, 'unit' => 'шт'],
            ['name' => 'Лук репчатый', 'quantity' => 1, 'unit' => 'шт'],
        ];
        
        $preferences = json_decode($this->option('prefs'), true) ?? [
            'diet' => 'none',
            'cuisine' => 'russian',
            'max_time' => 45,
        ];

        try {
            $this->info('📦 Продукты: ' . collect($products)->pluck('name')->join(', '));
            $this->info('⚙️  Пожелания: ' . json_encode($preferences, JSON_UNESCAPED_UNICODE));
            
            // Замер времени выполнения
            $start = microtime(true);
            
            $this->withSpinner('🔄 Запрос к GigaChat', function() use ($gigaChat, $products, $preferences) {
                return $gigaChat->generateRecipe($products, $preferences);
            });
            
            $elapsed = round(microtime(true) - $start, 2);
            $recipe = $gigaChat->generateRecipe($products, $preferences);
            
            $this->newLine();
            $this->info("✅ Рецепт сгенерирован за {$elapsed} сек:");
            
            $this->table(
                ['Поле', 'Значение'],
                [
                    ['🍽️ Название', $recipe['title'] ?? 'N/A'],
                    ['📝 Описание', $recipe['description'] ?? 'N/A'],
                    ['⏱️ Время', ($recipe['cooking_time'] ?? 0) . ' мин'],
                    ['📊 Сложность', $recipe['difficulty'] ?? 'N/A'],
                    ['🥘 Ингредиентов', count($recipe['ingredients'] ?? [])],
                    ['👣 Шагов', count($recipe['instructions'] ?? [])],
                ]
            );
            
            // Детали ингредиентов
            if (!empty($recipe['ingredients'])) {
                $this->info('🛒 Ингредиенты:');
                foreach ($recipe['ingredients'] as $ing) {
                    $note = !empty($ing['note']) ? " ({$ing['note']})" : '';
                    $this->line("   • {$ing['name']}: {$ing['quantity']} {$ing['unit']}{$note}");
                }
            }
            
            // Советы
            if (!empty($recipe['tips'])) {
                $this->info('💡 Советы:');
                foreach ($recipe['tips'] as $tip) {
                    $this->line("   ◦ {$tip}");
                }
            }
            
            // Сохраняем тестовый рецепт в БД для проверки
            if ($this->confirm('💾 Сохранить рецепт в базу данных?', true)) {
                $this->saveTestRecipe($recipe, $products, $preferences);
            }
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("❌ Ошибка: {$e->getMessage()}");
            
            // Показываем детали для отладки
            if ($this->output->isVerbose()) {
                $this->info('Stack trace:');
                $this->line($e->getTraceAsString());
            }
            
            Log::error('GigaChat test failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            
            return self::FAILURE;
        }
    }
    
    /**
     * Отображение спиннера во время выполнения задачи
     * PHP-версия без setInterval
     */
    protected function withSpinner(string $message, callable $callback): void
    {
        $spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $i = 0;
        $continue = true;
        $result = null;
        $error = null;
        
        // Запускаем спиннер в отдельном потоке вывода
        while ($continue) {
            $this->output->write("\r{$message} " . $spinner[$i % count($spinner)]);
            usleep(100000); // 100ms
            $i++;
            
            // Проверяем, не завершилась ли задача (через флаг)
            if (!$continue) break;
        }
        
        // Выполняем callback
        try {
            $result = $callback();
        } catch (\Throwable $e) {
            $error = $e;
        } finally {
            $continue = false;
            // Финальный вывод
            $this->output->write("\r{$message} " . ($error ? '❌' : '✅') . "   \n");
        }
        
        if ($error) {
            throw $error;
        }
    }
    
    /**
     * Сохранение тестового рецепта в БД
     */
    protected function saveTestRecipe(array $recipe, array $products, array $preferences): void
    {
        try {
            $model = \App\Models\GeneratedRecipe::create([
                'user_id' => 1, // тестовый пользователь
                'title' => $recipe['title'] ?? 'Тестовый рецепт',
                'description' => $recipe['description'] ?? '',
                'ingredients' => $recipe['ingredients'] ?? [],
                'instructions' => $recipe['instructions'] ?? [],
                'cooking_time' => $recipe['cooking_time'] ?? 30,
                'difficulty' => $recipe['difficulty'] ?? 'medium',
                'preferences' => $preferences,
                'products_used' => $products,
                'gigachat_raw_response' => $recipe, // для отладки
            ]);
            
            $this->info("✅ Рецепт сохранён в БД с ID: {$model->id}");
        } catch (\Exception $e) {
            $this->warn("⚠️ Не удалось сохранить в БД: {$e->getMessage()}");
        }
    }
}