# Service Interface (for other microservices)

Base URL (inside контейнера):
- `http://<service-host>:<service-port>`
- API routes live under `/api`

## Healthcheck

`GET /health`

## Generate recipe

`POST /api/v1/recipes/generate`

Request JSON:
```json
{
  "user_id": 1,
  "products": [
    { "name": "Куриное филе", "quantity": 300, "unit": "г" },
    { "name": "Картофель", "quantity": 4, "unit": "шт" }
  ],
  "preferences": {
    "diet": "none",
    "cuisine": "russian",
    "max_time": 45
  }
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "....",
    "description": "....",
    "ingredients": [{ "name": "....", "quantity": 123, "unit": "г" }],
    "instructions": [{ "step": 1, "description": "..." }],
    "cooking_time": "30",
    "difficulty": "easy",
    "preferences": { "diet": "none", "max_time": 45 },
    "products_used": [{ "name": "....", "quantity": 123, "unit": "г" }]
  }
}
```

Contract specification:
- `openapi/recipe-gen.yaml`

