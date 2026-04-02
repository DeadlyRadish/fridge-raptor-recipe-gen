<?php

namespace Tests\Unit;

use App\Services\GigaChat\GigaChatService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GigaChatServiceTest extends TestCase
{
    public function test_generate_recipe_performs_oauth_form_request_and_chat_completion(): void
    {
        config()->set('services.gigachat.auth_url', 'https://ngw.devices.sberbank.ru:9443');
        config()->set('services.gigachat.api_url', 'https://gigachat.devices.sberbank.ru/api/v1');
        config()->set('services.gigachat.client_id', 'client-id');
        config()->set('services.gigachat.secret', 'secret');
        config()->set('services.gigachat.scope', 'GIGACHAT_API_PERS');
        config()->set('services.gigachat.model', 'GigaChat');

        $authCalls = 0;
        $chatCalls = 0;

        Http::fake(function (Request $request) use (&$authCalls, &$chatCalls) {
            $url = (string) $request->url();

            if ($request->method() === 'POST' && str_ends_with($url, '/api/v2/oauth')) {
                $authCalls++;

                // Важно: OAuth должен отправляться как form-urlencoded.
                $body = (string) $request->body();
                parse_str($body, $params);

                $this->assertArrayHasKey('scope', $params);
                $this->assertSame('GIGACHAT_API_PERS', $params['scope']);

                return Http::response([
                    'access_token' => 'token-123',
                    // expires_at приходит в миллисекундах
                    'expires_at' => (time() + 3600) * 1000,
                ], 200);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/chat/completions')) {
                $chatCalls++;

                $payload = $request->data();
                $this->assertSame('GigaChat', $payload['model'] ?? null);

                $prompt = $payload['messages'][0]['content'] ?? '';
                $this->assertStringContainsString('- Курица (300 г)', $prompt);
                $this->assertStringContainsString('"max_time":30', $prompt);

                $content = json_encode([
                    'title' => 'Тестовый рецепт',
                    'description' => 'Описание',
                    'ingredients' => [['name' => 'Хлеб', 'quantity' => 1, 'unit' => 'шт']],
                    'instructions' => [['step' => 1, 'description' => 'Сделать тест']],
                    'cooking_time' => 30,
                    'difficulty' => 'easy',
                    'tips' => ['Совет'],
                ], JSON_UNESCAPED_UNICODE);

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => "```json\n{$content}\n```",
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected request'], 404);
        });

        $service = new GigaChatService();
        $result = $service->generateRecipe(
            [
                ['name' => 'Курица', 'quantity' => 300, 'unit' => 'г'],
            ],
            ['max_time' => 30]
        );

        $this->assertSame(1, $authCalls);
        $this->assertSame(1, $chatCalls);
        $this->assertSame('Тестовый рецепт', $result['title'] ?? null);
        $this->assertSame('easy', $result['difficulty'] ?? null);
        $this->assertSame('Хлеб', $result['ingredients'][0]['name'] ?? null);
        $this->assertSame('Сделать тест', $result['instructions'][0]['description'] ?? null);
    }

    public function test_generate_recipe_caches_access_token_until_expired(): void
    {
        config()->set('services.gigachat.auth_url', 'https://ngw.devices.sberbank.ru:9443');
        config()->set('services.gigachat.api_url', 'https://gigachat.devices.sberbank.ru/api/v1');
        config()->set('services.gigachat.client_id', 'client-id');
        config()->set('services.gigachat.secret', 'secret');
        config()->set('services.gigachat.scope', 'GIGACHAT_API_PERS');
        config()->set('services.gigachat.model', 'GigaChat');

        $authCalls = 0;
        $chatCalls = 0;

        Http::fake(function (Request $request) use (&$authCalls, &$chatCalls) {
            $url = (string) $request->url();

            if ($request->method() === 'POST' && str_ends_with($url, '/api/v2/oauth')) {
                $authCalls++;

                return Http::response([
                    'access_token' => 'token-123',
                    'expires_at' => (time() + 3600) * 1000,
                ], 200);
            }

            if ($request->method() === 'POST' && str_ends_with($url, '/chat/completions')) {
                $chatCalls++;

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => '{ "title": "R", "description": "D", "ingredients": [], "instructions": [], "cooking_time": 30, "difficulty": "easy", "tips": [] }',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected request'], 404);
        });

        $service = new GigaChatService();

        $service->generateRecipe(
            [['name' => 'Курица', 'quantity' => 300, 'unit' => 'г']],
            []
        );

        $service->generateRecipe(
            [['name' => 'Курица', 'quantity' => 300, 'unit' => 'г']],
            []
        );

        // Должно быть одно OAuth-запроса в рамках одного инстанса сервиса.
        $this->assertSame(1, $authCalls);
        $this->assertSame(2, $chatCalls);
    }

    public function test_generate_recipe_falls_back_to_default_when_content_has_no_json_object(): void
    {
        config()->set('services.gigachat.auth_url', 'https://ngw.devices.sberbank.ru:9443');
        config()->set('services.gigachat.api_url', 'https://gigachat.devices.sberbank.ru/api/v1');
        config()->set('services.gigachat.client_id', 'client-id');
        config()->set('services.gigachat.secret', 'secret');
        config()->set('services.gigachat.scope', 'GIGACHAT_API_PERS');
        config()->set('services.gigachat.model', 'GigaChat');

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && str_ends_with((string) $request->url(), '/api/v2/oauth')) {
                return Http::response([
                    'access_token' => 'token-123',
                    'expires_at' => (time() + 3600) * 1000,
                ], 200);
            }

            if ($request->method() === 'POST' && str_ends_with((string) $request->url(), '/chat/completions')) {
                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'Not JSON at all',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected request'], 404);
        });

        $service = new GigaChatService();

        $result = $service->generateRecipe(
            [['name' => 'Курица', 'quantity' => 300, 'unit' => 'г']],
            []
        );

        $this->assertSame('Рецепт из доступных продуктов', $result['title'] ?? null);
        $this->assertSame([], $result['ingredients'] ?? null);
        $this->assertSame('Not JSON at all', $result['instructions'][0]['description'] ?? null);
        $this->assertSame(30, $result['cooking_time'] ?? null);
        $this->assertSame('medium', $result['difficulty'] ?? null);
    }

    public function test_generate_recipe_falls_back_to_default_when_json_is_invalid(): void
    {
        config()->set('services.gigachat.auth_url', 'https://ngw.devices.sberbank.ru:9443');
        config()->set('services.gigachat.api_url', 'https://gigachat.devices.sberbank.ru/api/v1');
        config()->set('services.gigachat.client_id', 'client-id');
        config()->set('services.gigachat.secret', 'secret');
        config()->set('services.gigachat.scope', 'GIGACHAT_API_PERS');
        config()->set('services.gigachat.model', 'GigaChat');

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && str_ends_with((string) $request->url(), '/api/v2/oauth')) {
                return Http::response([
                    'access_token' => 'token-123',
                    'expires_at' => (time() + 3600) * 1000,
                ], 200);
            }

            if ($request->method() === 'POST' && str_ends_with((string) $request->url(), '/chat/completions')) {
                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                // Невалидный JSON внутри фигурных скобок.
                                'content' => '{invalid json}',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected request'], 404);
        });

        $service = new GigaChatService();

        $result = $service->generateRecipe(
            [['name' => 'Курица', 'quantity' => 300, 'unit' => 'г']],
            []
        );

        $this->assertSame('Рецепт из доступных продуктов', $result['title'] ?? null);
        $this->assertSame([], $result['ingredients'] ?? null);
        $this->assertSame('{invalid json}', $result['instructions'][0]['description'] ?? null);
        $this->assertSame(30, $result['cooking_time'] ?? null);
        $this->assertSame('medium', $result['difficulty'] ?? null);
    }

    public function test_generate_recipe_refreshes_access_token_when_expired(): void
    {
        config()->set('services.gigachat.auth_url', 'https://ngw.devices.sberbank.ru:9443');
        config()->set('services.gigachat.api_url', 'https://gigachat.devices.sberbank.ru/api/v1');
        config()->set('services.gigachat.client_id', 'client-id');
        config()->set('services.gigachat.secret', 'secret');
        config()->set('services.gigachat.scope', 'GIGACHAT_API_PERS');
        config()->set('services.gigachat.model', 'GigaChat');

        $authCalls = 0;
        $chatCalls = 0;

        Http::fake(function (Request $request) use (&$authCalls, &$chatCalls) {
            if ($request->method() === 'POST' && str_ends_with((string) $request->url(), '/api/v2/oauth')) {
                $authCalls++;

                // Просрочим токен практически сразу.
                return Http::response([
                    'access_token' => 'token-123',
                    'expires_at' => (time() + 1) * 1000,
                ], 200);
            }

            if ($request->method() === 'POST' && str_ends_with((string) $request->url(), '/chat/completions')) {
                $chatCalls++;

                return Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => '{ "title": "R", "description": "D", "ingredients": [], "instructions": [], "cooking_time": 30, "difficulty": "easy", "tips": [] }',
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected request'], 404);
        });

        $service = new GigaChatService();

        $service->generateRecipe([['name' => 'Курица', 'quantity' => 300, 'unit' => 'г']], []);
        $service->generateRecipe([['name' => 'Курица', 'quantity' => 300, 'unit' => 'г']], []);

        // Должно быть 2 OAuth-запроса, т.к. токен истёк.
        $this->assertSame(2, $authCalls);
        $this->assertSame(2, $chatCalls);
    }
}

