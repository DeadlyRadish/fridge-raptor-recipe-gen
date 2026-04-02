# fridge-raptor-recipe-gen

## FridgeRaptot
Генерация рецептов из списка продутов, полученных из базы данных холодильника

## Docker запуск (API + PostgreSQL)

1. Заполните `api/.env.docker` (минимум `GIGACHAT_CLIENT_ID` и `GIGACHAT_SECRET`).
2. В корне проекта выполните:
   - `docker compose up --build -d`
3. API будет доступно по `http://localhost:8000`, healthcheck:
   - `GET http://localhost:8000/api/health`

Важно:
- В контейнерном режиме API подключается к БД по `DB_HOST=postgres` (имя сервиса в `docker-compose.yml`), а не по `127.0.0.1`.
- Миграции выполняются автоматически при старте API-контейнера.
