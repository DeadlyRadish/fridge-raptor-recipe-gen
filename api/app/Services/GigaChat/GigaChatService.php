<?php

namespace App\Services\GigaChat;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class GigaChatService
{
    protected string $authUrl;
    protected string $apiUrl;
    protected string $clientId;
    protected string $secret;
    protected string $scope;
    protected string $model;
    protected ?string $accessToken = null;
    protected ?int $tokenExpiresAt = null;
    protected bool $verifySsl;

    public function __construct()
    {
        $this->authUrl = rtrim(config('services.gigachat.auth_url'), '/');
        $this->apiUrl = rtrim(config('services.gigachat.api_url'), '/');
        $this->clientId = config('services.gigachat.client_id');
        $this->secret = config('services.gigachat.secret');
        // Сбер ожидает именно корректный формат scope (строка form-urlencoded).
        $this->scope = trim((string) config('services.gigachat.scope', 'GIGACHAT_API_PERS'));
        $this->model = config('services.gigachat.model', 'GigaChat');
        $this->verifySsl = env('GIGACHAT_VERIFY_SSL', false); // false для разработки
    }

    public function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $authKey = base64_encode("{$this->clientId}:{$this->secret}");
        $authKey = str_replace(["\r", "\n"], '', $authKey);
        $rqUid = Uuid::uuid4()->toString();

        $response = Http::withOptions([
            'timeout' => 20,
            'connect_timeout' => 10,
            'verify' => $this->verifySsl,
        ])->asForm()->withHeaders([
            'Accept' => 'application/json',
            'RqUID' => $rqUid,
            'Authorization' => "Basic {$authKey}",
        ])->post("{$this->authUrl}/api/v2/oauth", [
            'scope' => $this->scope,
        ]);

        if (!$response->successful()) {
            Log::error('GigaChat auth failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 200),
            ]);
            throw new \RuntimeException("Auth failed: {$response->status()}");
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];
        $expiresAt = $data['expires_at'] ?? (time() + 1800) * 1000;
        $this->tokenExpiresAt = $expiresAt > 1e12 
            ? intdiv($expiresAt, 1000) - 60 
            : $expiresAt - 60;

        return $this->accessToken;
    }

    public function generateRecipe(array $products, array $preferences = []): array
    {
        $token = $this->getAccessToken();
        $prompt = $this->buildRecipePrompt($products, $preferences);

        $response = Http::withOptions([
            'timeout' => 60, // Генерация может занять время
            'connect_timeout' => 15,
            'verify' => $this->verifySsl,
        ])->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("{$this->apiUrl}/chat/completions", [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.3,
        ]);

        if (!$response->successful()) {
            Log::error('GigaChat API call failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 300),
            ]);
            throw new \RuntimeException("GigaChat API error: {$response->status()}");
        }

        $result = $response->json();
        $content = $result['choices'][0]['message']['content'] ?? '';
        
        return $this->parseRecipeResponse($content);
    }

    protected function buildRecipePrompt(array $products, array $preferences): string
    {
        $productsList = collect($products)->map(fn($p) => 
            "- {$p['name']} ({$p['quantity']} {$p['unit']})"
        )->join("\n");

        $prefsText = !empty($preferences) 
            ? "Пожелания: " . json_encode($preferences, JSON_UNESCAPED_UNICODE) 
            : "Без особых пожеланий";

        return <<<PROMPT
Ты — профессиональный шеф-повар. Сгенерируй ОДИН рецепт на основе продуктов:

{$productsList}

{$prefsText}

ТРЕБОВАНИЯ:
1. Верни ответ СТРОГО в формате JSON без markdown
2. Структура:
{
  "title": "Название рецепта",
  "description": "Краткое описание",
  "ingredients": [{"name": "продукт", "quantity": 100, "unit": "г", "note": "опционально"}],
  "instructions": [{"step": 1, "description": "Шаг приготовления"}],
  "cooking_time": 30,
  "difficulty": "easy|medium|hard",
  "tips": ["совет 1"]
}
3. Используй ТОЛЬКО продукты из списка
4. cooking_time — в минутах, целое число
5. Все тексты на русском
6. Начни ответ сразу с {

Ответ:
PROMPT;
    }

    protected function parseRecipeResponse(string $content): array
    {
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content));
        
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }
        }
        
        // Fallback
        return [
            'title' => 'Рецепт из доступных продуктов',
            'description' => $content,
            'ingredients' => [],
            'instructions' => [['step' => 1, 'description' => $content]],
            'cooking_time' => 30,
            'difficulty' => 'medium',
            'tips' => [],
        ];
    }
}