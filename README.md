# Fridge Raptor Recipe Gen API

API-сервис для генерации рецептов на основе доступных продуктов с использованием AI (GigaChat/Sber).

## 📖 Описание

**Fridge Raptor Recipe Gen** — это backend-сервис, который помогает пользователям создавать кулинарные рецепты на основе продуктов, которые есть у них в холодильнике. Сервис использует нейросеть GigaChat от Сбера для генерации уникальных рецептов с учётом предпочтений пользователя (диета, сложность, время приготовления и т.д.).

### Основные возможности

- 🤖 **AI-генерация рецептов** — создание рецептов через GigaChat API
- 📝 **Управление рецептами** — CRUD операции для созданных рецептов
- 👤 **Привязка к пользователю** — сохранение истории рецептов по user_id
- 🔍 **Фильтрация** — получение рецептов конкретного пользователя
- 💾 **Постоянное хранение** — PostgreSQL для надёжного хранения данных
- 🐳 **Docker-контейнеризация** — простой запуск и развёртывание

---

## 🚀 Быстрый старт

### Предварительные требования

- [Docker](https://www.docker.com/) (версия 20.10+)
- [Docker Compose](https://docs.docker.com/compose/) (версия 2.0+)

### 1. Клонирование репозитория

```bash
git clone <repository-url>
cd fridge-raptor-recipe-gen
```

### 2. Настройка переменных окружения

Скопируйте файл конфигурации и настройте параметры GigaChat API:

```bash
cp api/.env.docker api/.env.docker.local
```

Отредактируйте `api/.env.docker.local` и добавьте ваши credentials:

```env
GIGACHAT_CLIENT_ID=your_client_id
GIGACHAT_SECRET=your_secret_key
GIGACHAT_SCOPE=GIGACHAT_API_PERS
GIGACHAT_AUTH_URL=https://ngw.devices.sberbank.ru:9443
GIGACHAT_API_URL=https://gigachat.devices.sberbank.ru/api/v1
GIGACHAT_MODEL=GigaChat
GIGACHAT_VERIFY_SSL=false
```

> ⚠️ **Важно:** Для получения `CLIENT_ID` и `SECRET` зарегистрируйтесь в [Sber Developer Portal](https://developers.sber.ru/).

### 3. Запуск контейнеров

#### Первый запуск (сборка и запуск всех сервисов):

```bash
docker compose up --build -d
```

Команда выполнит:
- Сборку образа API (`php:8.4-cli` + зависимости)
- Запуск PostgreSQL 15
- Автоматическую миграцию базы данных
- Генерацию `APP_KEY` (если не задан)

#### Проверка статуса:

```bash
docker compose ps
```

Ожидаемый вывод:
```
NAME                 STATUS                    PORTS
recipe-api           Up (healthy)              0.0.0.0:8000->8000/tcp
recipe-postgres      Up (healthy)              0.0.0.0:5432->5432/tcp
```

### 4. Остановка контейнеров

#### Временная остановка (без удаления данных):

```bash
docker compose stop
```

#### Полная остановка с удалением контейнеров:

```bash
docker compose down
```

#### Полная очистка (включая volumes и образы):

```bash
docker compose down -v --rmi all
```

> ⚠️ **Внимание:** Команда `down -v` удалит все данные базы данных!

---

## 📡 API Endpoints

Базовый URL: `http://localhost:8000`

### Health Check

Проверка работоспособности сервиса.

| Метод | Endpoint | Описание |
|-------|----------|----------|
| `GET` | `/health` | Проверка статуса сервиса |

**Пример запроса:**
```bash
curl http://localhost:8000/api/health
```

**Пример ответа:**
```json
{
  "status": "ok",
  "timestamp": "2025-02-25T12:00:00+00:00",
  "environment": "local"
}
```

---

### Рецепты

#### 1. Получить список всех рецептов

| Метод | Endpoint | Описание |
|-------|----------|----------|
| `GET` | `/api/v1/recipes` | Пагинация всех рецептов (20 на странице) |

**Query параметры:**
- `user_id` (optional) — фильтрация по ID пользователя

**Пример запроса:**
```bash
# Все рецепты
curl http://localhost:8000/api/v1/recipes

# Рецепты конкретного пользователя
curl http://localhost:8000/api/v1/recipes?user_id=123
```

**Пример ответа:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "user_id": 123,
        "title": "Курица с овощами",
        "description": "Вкусное и полезное блюдо",
        "ingredients": [...],
        "instructions": [...],
        "cooking_time": "30 минут",
        "difficulty": "средне",
        "created_at": "2025-02-25T12:00:00.000000Z",
        "updated_at": "2025-02-25T12:00:00.000000Z"
      }
    ],
    "total": 15,
    "per_page": 20,
    "last_page": 1
  }
}
```

---

#### 2. Получить рецепт по ID

| Метод | Endpoint | Описание |
|-------|----------|----------|
| `GET` | `/api/v1/recipes/{id}` | Получение одного рецепта |

**Пример запроса:**
```bash
curl http://localhost:8000/api/v1/recipes/1
```

**Пример ответа:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 123,
    "title": "Курица с овощами",
    "description": "Вкусное и полезное блюдо...",
    "ingredients": [
      {
        "name": "куриное филе",
        "quantity": 500,
        "unit": "г",
        "note": "нарезать кубиками"
      },
      {
        "name": "помидоры",
        "quantity": 2,
        "unit": "шт",
        "note": null
      }
    ],
    "instructions": [
      {
        "step": 1,
        "description": "Нарезать курицу кубиками"
      },
      {
        "step": 2,
        "description": "Обжарить на сковороде 5 минут"
      }
    ],
    "cooking_time": 30,
    "difficulty": "medium",
    "preferences": {
      "diet": "low-carb",
      "spicy": false
    },
    "products_used": [
      {
        "name": "куриное филе",
        "quantity": 500,
        "unit": "г"
      }
    ],
    "created_at": "2025-02-25T12:00:00.000000Z",
    "updated_at": "2025-02-25T12:00:00.000000Z"
  }
}
```

---

#### 3. Сгенерировать и создать рецепт

| Метод | Endpoint | Описание |
|-------|----------|----------|
| `POST` | `/api/v1/recipes` | Генерация рецепта через AI и сохранение |
| `POST` | `/api/v1/recipes/generate` | Альтернативный эндпоинт для генерации |

**Тело запроса:**

| Поле | Тип | Обязательное | Описание |
|------|-----|--------------|----------|
| `user_id` | integer | ❌ | ID пользователя для привязки |
| `products` | array | ✅ | Список продуктов (минимум 1) |
| `products[].name` | string | ✅ | Название продукта |
| `products[].quantity` | number | ✅ | Количество |
| `products[].unit` | string | ✅ | Единица измерения (г, кг, шт, мл) |
| `preferences` | object | ❌ | Дополнительные предпочтения |

**Пример запроса:**
```bash
curl -X POST http://localhost:8000/api/v1/recipes \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 123,
    "products": [
      {"name": "куриное филе", "quantity": 500, "unit": "г"},
      {"name": "помидоры", "quantity": 2, "unit": "шт"},
      {"name": "сыр моцарелла", "quantity": 100, "unit": "г"},
      {"name": "базилик", "quantity": 5, "unit": "г"}
    ],
    "preferences": {
      "diet": "low-carb",
      "cooking_method": "запекание",
      "max_time": 45,
      "spicy": false
    }
  }'
```

**Пример ответа (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "user_id": 123,
    "title": "Капрезе с курицей",
    "description": "Лёгкое итальянское блюдо с запечённой курицей...",
    "ingredients": [
      {
        "name": "куриное филе",
        "quantity": 500,
        "unit": "г",
        "note": "нарезать ломтиками"
      },
      {
        "name": "помидоры",
        "quantity": 2,
        "unit": "шт",
        "note": "крупно нарезать"
      },
      {
        "name": "сыр моцарелла",
        "quantity": 100,
        "unit": "г",
        "note": "нарезать кружочками"
      },
      {
        "name": "базилик",
        "quantity": 5,
        "unit": "г",
        "note": "для украшения"
      }
    ],
    "instructions": [
      {
        "step": 1,
        "description": "Разогреть духовку до 200°C"
      },
      {
        "step": 2,
        "description": "Выложить курицу, помидоры и сыр слоями"
      },
      {
        "step": 3,
        "description": "Запекать 25 минут до золотистой корочки"
      },
      {
        "step": 4,
        "description": "Украсить свежим базиликом перед подачей"
      }
    ],
    "cooking_time": 30,
    "difficulty": "easy",
    "preferences": {
      "diet": "low-carb",
      "cooking_method": "запекание",
      "max_time": 45,
      "spicy": false
    },
    "products_used": [
      {"name": "куриное филе", "quantity": 500, "unit": "г"},
      {"name": "помидоры", "quantity": 2, "unit": "шт"},
      {"name": "сыр моцарелла", "quantity": 100, "unit": "г"},
      {"name": "базилик", "quantity": 5, "unit": "г"}
    ],
    "created_at": "2025-02-25T12:30:00.000000Z",
    "updated_at": "2025-02-25T12:30:00.000000Z"
  }
}
```

**Пример ответа при ошибке (500 Internal Server Error):**
```json
{
  "success": false,
  "error": "Ошибка генерации рецепта: Auth failed: 401"
}
```

---

#### 4. Обновить рецепт

| Метод | Endpoint | Описание |
|-------|----------|----------|
| `PUT/PATCH` | `/api/v1/recipes/{id}` | Обновление рецепта |

> ⚠️ **Примечание:** В текущей версии метод возвращает ошибку 405 (Method Not Allowed).

**Пример ответа:**
```json
{
  "success": false,
  "error": "Метод не поддерживается"
}
```

---

#### 5. Удалить рецепт

| Метод | Endpoint | Описание |
|-------|----------|----------|
| `DELETE` | `/api/v1/recipes/{id}` | Удаление рецепта по ID |

**Пример запроса:**
```bash
curl -X DELETE http://localhost:8000/api/v1/recipes/1
```

**Пример ответа:**
```json
{
  "success": true,
  "message": "Рецепт удален"
}
```

---

#### 6. Получить рецепты пользователя

| Метод | Endpoint | Описание |
|-------|----------|----------|
| `GET` | `/api/v1/users/{user_id}/recipes` | Все рецепты конкретного пользователя |

**Пример запроса:**
```bash
curl http://localhost:8000/api/v1/users/123/recipes
```

**Ответ:** Аналогичен endpoint'у `/api/v1/recipes?user_id=123`

---

## 🗄️ База данных

### Структура таблицы `generated_recipes`

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint | Первичный ключ |
| `user_id` | bigint (nullable) | ID пользователя |
| `title` | string | Название рецепта |
| `description` | text (nullable) | Краткое описание |
| `ingredients` | json | Массив ингредиентов |
| `instructions` | json | Массив шагов приготовления |
| `cooking_time` | string | Время приготовления (по умолчанию "30 минут") |
| `difficulty` | string | Сложность (easy/medium/hard) |
| `preferences` | json (nullable) | Предпочтения пользователя |
| `products_used` | json (nullable) | Использованные продукты |
| `created_at` | timestamp | Дата создания |
| `updated_at` | timestamp | Дата обновления |

### Подключение к PostgreSQL

```bash
# Из хоста
psql -h localhost -p 5432 -U postgres -d recipe_service

# Из контейнера API
docker compose exec api psql -h postgres -U postgres -d recipe_service
```

---

## 🔧 Разработка и отладка

### Просмотр логов

```bash
# Логи API
docker compose logs -f api

# Логи базы данных
docker compose logs -f postgres

# Все логи
docker compose logs -f
```

### Выполнение команд внутри контейнера

```bash
# Войти в интерактивную оболочку
docker compose exec api bash

# Выполнить artisan команду
docker compose exec api php artisan list

# Запустить миграции заново
docker compose exec api php artisan migrate:fresh --seed

# Очистить кэш
docker compose exec api php artisan cache:clear
docker compose exec api php artisan config:clear
```

### Тестирование API

```bash
# Запустить тесты
docker compose exec api composer test

# Или напрямую
docker compose exec api php artisan test

# Запустить тесты с покрытием
docker compose exec api php artisan test --coverage
```

---

## 🔍 Код-качество и статический анализ

Проект использует современные инструменты для поддержания качества кода:

### 1. Статический анализ кода (PHPStan + Larastan)

[PHPStan](https://phpstan.org/) — инструмент статического анализа PHP кода для поиска ошибок без запуска кода.  
[Larastan](https://github.com/larastan/larastan) — расширение PHPStan для Laravel, которое понимает магические методы Eloquent, фасады и другие особенности фреймворка.

**Конфигурация:** `api/phpstan.neon`

```bash
# Запустить анализ через composer скрипт
docker compose exec api composer phpstan

# Или напрямую через vendor/bin
docker compose exec api ./vendor/bin/phpstan analyse

# Запуск с конкретным уровнем строгости (0-9, где 9 - самый строгий)
docker compose exec api ./vendor/bin/phpstan analyse --level 7

# Запуск с выводом деталей ошибок
docker compose exec api ./vendor/bin/phpstan analyse --error-format=table

# Очистить кэш PHPStan (если возникают проблемы с устаревшими результатами)
docker compose exec api ./vendor/bin/phpstan clear-result-cache
```

**Уровни анализа:**
- Уровень 5 (по умолчанию) — баланс между строгостью и практичностью
- Уровни 6-8 — более строгая проверка типов
- Уровень 9 — максимальная строгость (требует идеальной типизации)

**Игнорирование ошибок:**  
Некоторые ошибки игнорируются в `phpstan.neon` (например, динамические методы Eloquent). При необходимости можно добавить свои правила в секцию `ignoreErrors`.

---

### 2. Code Style Fixer (Laravel Pint)

[Laravel Pint](https://laravel.com/docs/pint) — официальный инструмент для автоматического форматирования кода в стиле Laravel.

**Конфигурация:** Используется стандартная конфигурация Laravel (можно переопределить в `pint.json`)

```bash
# Автоматически исправить стиль кода
docker compose exec api ./vendor/bin/pint

# Проверить код без исправлений (dry-run)
docker compose exec api ./vendor/bin/pint --test

# Проверить конкретные файлы или директории
docker compose exec api ./vendor/bin/pint app/Models
docker compose exec api ./vendor/bin/pint app/Http/Controllers/RecipeController.php

# Применить форматирование с verbose выводом
docker compose exec api ./vendor/bin/pint -v
```

**Рекомендуемый workflow:**
1. Перед каждым коммитом запускайте `composer phpstan` для проверки на ошибки
2. Затем запускайте `./vendor/bin/pint` для форматирования кода
3. Убедитесь, что все тесты проходят: `composer test`

---

### 3. Пре-коммит хуки (рекомендация)

Для автоматизации проверок рекомендуется настроить Git hooks (например, через [husky](https://typicode.github.io/husky/)):

```bash
# Пример pre-commit хука (.git/hooks/pre-commit)
#!/bin/bash
echo "Running PHPStan..."
docker compose exec api composer phpstan || exit 1

echo "Running Laravel Pint..."
docker compose exec api ./vendor/bin/pint --test || exit 1

echo "Running tests..."
docker compose exec api composer test || exit 1

echo "✓ All checks passed!"
```

Сделайте хук исполняемым:
```bash
chmod +x .git/hooks/pre-commit
```

---

## 🔐 Безопасность

### CORS

Для настройки CORS отредактируйте `config/cors.php` или добавьте переменные окружения:

```env
FRONTEND_URL=http://localhost:3000
ALLOWED_ORIGINS=http://localhost:3000,https://yourdomain.com
```

### GigaChat API Keys

Никогда не коммитьте файлы с реальными ключами! Используйте `.env` файлы и добавьте их в `.gitignore`.

---

## 📦 Архитектура

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Клиент        │────▶│   Laravel API    │────▶│   GigaChat API  │
│   (Frontend)    │     │   (PHP 8.4)      │     │   (Sber AI)     │
└─────────────────┘     └────────┬─────────┘     └─────────────────┘
                                 │
                                 ▼
                        ┌──────────────────┐
                        │   PostgreSQL 15  │
                        │   (Данные)       │
                        └──────────────────┘
```

### Технологический стек

- **Язык:** PHP 8.4
- **Фреймворк:** Laravel 12
- **База данных:** PostgreSQL 15
- **AI провайдер:** GigaChat (Sber)
- **Контейнеризация:** Docker + Docker Compose

---

## ❓ Troubleshooting

### Ошибка: "Database connection timeout"

1. Убедитесь, что PostgreSQL запущен:
   ```bash
   docker compose ps postgres
   ```

2. Проверьте логи базы данных:
   ```bash
   docker compose logs postgres
   ```

3. Перезапустите сервисы:
   ```bash
   docker compose restart
   ```

### Ошибка: "Auth failed: 401" при генерации рецепта

1. Проверьте корректность `GIGACHAT_CLIENT_ID` и `GIGACHAT_SECRET` в `.env.docker`
2. Убедитесь, что ваш аккаунт Sber Developer активен
3. Проверьте scope доступа: `GIGACHAT_SCOPE=GIGACHAT_API_PERS`

### Ошибка: "Container already exists"

```bash
# Удалить существующий контейнер
docker rm -f recipe-postgres
docker rm -f recipe-api

# Запустить заново
docker compose up -d
```

### Ошибка: "Could not open input file: artisan"

Пересоберите образ с правильным порядком копирования файлов:

```bash
docker compose build --no-cache
docker compose up -d
```

---

## 📄 Лицензия

Проект распространяется под лицензией MIT.

---