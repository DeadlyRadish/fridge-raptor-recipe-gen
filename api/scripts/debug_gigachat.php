<?php
// api/scripts/debug_gigachat.php
// Полностью рабочий скрипт для Sber GigaChat API

echo "🔍 Диагностика Sber GigaChat API\n";
echo "=================================\n\n";

// Загрузка .env
$envFile = __DIR__ . '/../.env';
$envVars = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}
function env($key, $default = null) {
    global $envVars;
    $val = $envVars[$key] ?? $default;
    return is_string($val) ? trim($val, " \t\n\r\0\x0B\"'") : $val;
}

// Проверка переменных
echo "📋 Конфигурация:\n";
echo "  Client ID: " . substr(env('GIGACHAT_CLIENT_ID'), 0, 15) . "...\n";
echo "  Secret: " . substr(env('GIGACHAT_SECRET'), 0, 15) . "...\n";
echo "  Scope: " . env('GIGACHAT_SCOPE') . "\n";
echo "  Auth URL: " . env('GIGACHAT_AUTH_URL') . "\n";
echo "  API URL: " . env('GIGACHAT_API_URL') . "\n\n";

// 1. OAuth авторизация
echo "🔐 Шаг 1: Получение токена...\n";
$authUrl = rtrim(env('GIGACHAT_AUTH_URL'), '/') . '/api/v2/oauth';
$clientId = env('GIGACHAT_CLIENT_ID');
$secret = env('GIGACHAT_SECRET');
$scope = env('GIGACHAT_SCOPE', 'GIGACHAT_API_PERS');

$authKey = base64_encode("{$clientId}:{$secret}");
$authKey = str_replace(["\r", "\n"], '', $authKey);
$uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000, mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $authUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['scope' => $scope]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'RqUID: ' . $uuid,
        'Authorization: Basic ' . $authKey,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false, // 🔧 Для разработки
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode != 200) {
    die("❌ Auth failed: {$curlError} (HTTP {$httpCode})\nResponse: {$response}\n");
}

$data = json_decode($response, true);
$token = $data['access_token'];
echo "✅ Токен получен: " . substr($token, 0, 30) . "...\n";
echo "   Expires: " . date('Y-m-d H:i:s', intdiv($data['expires_at'] ?? time()+1800, 1000)) . "\n\n";

// 2. Генерация рецепта
echo "🍳 Шаг 2: Генерация рецепта...\n";
$apiUrl = rtrim(env('GIGACHAT_API_URL'), '/') . '/chat/completions';

$products = [
    ['name' => 'Куриное филе', 'quantity' => 300, 'unit' => 'г'],
    ['name' => 'Картофель', 'quantity' => 4, 'unit' => 'шт'],
    ['name' => 'Морковь', 'quantity' => 1, 'unit' => 'шт'],
];
$productsList = implode("\n", array_map(fn($p) => "- {$p['name']} ({$p['quantity']} {$p['unit']})", $products));

$prompt = <<<PROMPT
Ты — шеф-повар. Сгенерируй ОДИН рецепт из продуктов:
{$productsList}

Верни СТРОГО JSON без markdown:
{
  "title": "Название",
  "description": "Описание",
  "ingredients": [{"name": "продукт", "quantity": 100, "unit": "г"}],
  "instructions": [{"step": 1, "description": "Шаг"}],
  "cooking_time": 30,
  "difficulty": "easy"
}
Используй только продукты из списка. Ответ на русском. Начни с {
PROMPT;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'model' => env('GIGACHAT_MODEL', 'GigaChat'),
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
    ]),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60, // Генерация может быть долгой
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false, // 🔧 Для разработки
    CURLOPT_SSL_VERIFYHOST => 0,
]);

echo "   🔄 Отправка запроса (может занять 10-30 сек)...\n";
$start = time();
$response = curl_exec($ch);
$elapsed = time() - $start;
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    die("❌ Chat error: {$curlError}\n");
}
if ($httpCode != 200) {
    die("❌ HTTP {$httpCode}\nResponse: {$response}\n");
}

echo "✅ Ответ получен за {$elapsed} сек\n\n";

$result = json_decode($response, true);
$content = $result['choices'][0]['message']['content'] ?? '';

// Парсинг JSON из ответа
$content = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content));
if (preg_match('/\{.*\}/s', $content, $matches)) {
    $recipe = json_decode($matches[0], true);
    if ($recipe) {
        echo "🍽️ Рецепт: {$recipe['title']}\n";
        echo "⏱️ Время: {$recipe['cooking_time']} мин, Сложность: {$recipe['difficulty']}\n";
        echo "🥘 Ингредиенты:\n";
        foreach ($recipe['ingredients'] ?? [] as $ing) {
            echo "   • {$ing['name']}: {$ing['quantity']} {$ing['unit']}\n";
        }
        echo "👣 Шаги:\n";
        foreach ($recipe['instructions'] ?? [] as $step) {
            echo "   {$step['step']}. {$step['description']}\n";
        }
    } else {
        echo "⚠️ Не удалось распарсить JSON:\n" . substr($content, 0, 300) . "\n";
    }
} else {
    echo "⚠️ Ответ не содержит JSON:\n" . substr($content, 0, 300) . "\n";
}

echo "\n✅ Диагностика завершена успешно!\n";