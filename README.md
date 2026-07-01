# Guised Up Real Connections Feed

[![PHP 8.2+](https://img.shields.io/badge/php-8.2+-777bb4.svg)](https://www.php.net/)
[![Laravel 11](https://img.shields.io/badge/laravel-11-ff2d20.svg)](https://laravel.com/)
[![Python 3.12+](https://img.shields.io/badge/python-3.12+-3776ab.svg)](https://www.python.org/)
[![Poetry](https://img.shields.io/badge/poetry-dependency%20management-blue.svg)](https://python-poetry.org/)
[![Expo](https://img.shields.io/badge/expo-react%20native-000020.svg)](https://expo.dev/)
[![License: Proprietary](https://img.shields.io/badge/license-proprietary-red.svg)](composer.json)

Production-ready take-home implementation for the Guised Up Real Connections
Feed. The project builds a personalized social feed that prefers authentic
posts, real relationships, semantic relevance, and recency instead of raw likes
or popularity.

The repository includes a Laravel API, PostgreSQL with pgvector, an internal
Python embedding service, an Expo React Native feed screen, tests, SQL answers,
and a technical solution document.

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Node.js 20+ and npm
- Python 3.12+ and Poetry if running embedding tests outside Docker

Host PHP is not required when using Docker.

### Installation

```bash
# 1. Go to the project
cd guised-up

# 2. Create local environment file
cp .env.example .env

# 3. Start the backend stack
docker compose up -d --build

# 4. Check running containers
docker compose ps

# 5. Open the API index
curl http://localhost:8000/api
```

Expected API response:

```json
{
  "name": "Guised Up Feed",
  "status": "ok",
  "auth": "Use POST /api/auth/login to create a Sanctum Bearer token."
}
```

### Run The Browser Preview

```bash
# 1. Create a Sanctum token for the seeded demo user
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"mithlesh@example.com","password":"password"}' \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["token"])')

# 2. Start Expo Web
cd mobile
npm install
EXPO_PUBLIC_API_URL=http://localhost:8000/api EXPO_PUBLIC_AUTH_TOKEN="$TOKEN" npm run web
```

Open the Web URL shown by Expo, usually:

```text
http://localhost:8081
```

If port `8081` is already in use, Expo will ask to use another port such as
`8082`. That is fine.

## Basic Usage

### Login

```bash
curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"mithlesh@example.com","password":"password"}'
```

The response contains a Sanctum token:

```json
{
  "token": "1|plain-text-token",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Mithlesh Upadhyay",
    "username": "mithlesh"
  }
}
```

Use that token as:

```text
Authorization: Bearer <token>
```

### Fetch Feed

```bash
curl -s http://localhost:8000/api/feed?page=1 \
  -H "Authorization: Bearer $TOKEN"
```

### Semantic Search

```bash
curl -s "http://localhost:8000/api/search?q=funny%20travel%20stories" \
  -H "Authorization: Bearer $TOKEN"
```

### Create Post

```bash
curl -s -X POST http://localhost:8000/api/posts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text":"Unfiltered walk through Old Delhi today. Got lost, found the best chai.","image_url":"https://example.com/chai.jpg"}'
```

### Log Interaction

```bash
curl -s -X POST http://localhost:8000/api/interactions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"post_id":1,"type":"reaction","metadata":{"reaction":"heart"}}'
```

## API Endpoint Map

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `GET` | `/api` | No | API index and available routes |
| `POST` | `/api/auth/login` | No | Create Sanctum Bearer token |
| `GET` | `/api/me` | Yes | Return current user |
| `GET` | `/api/feed?page=1` | Yes | Return personalized ranked feed |
| `GET` | `/api/search?q=query` | Yes | Return semantic vector matches |
| `POST` | `/api/posts` | Yes | Create post and store vector embedding |
| `POST` | `/api/interactions` | Yes | Log view, reply, or reaction |

## Seeded Users

| Email | Password | Notes |
|-------|----------|-------|
| `mithlesh@example.com` | `password` | Main reviewer/demo user |
| `prachi@example.com` | `password` | Seeded relationship user |
| `demo@example.com` | `password` | Seeded sample author |

## Features

- Personalized feed ranking without popularity scoring
- Laravel Sanctum token authentication
- PostgreSQL schema with pgvector embeddings
- Internal FastAPI embedding service
- Service-to-service token protection for embeddings
- Deterministic local embeddings for reproducible review
- Semantic search over post vectors
- Relationship-depth scoring from real interactions
- Authenticity scoring for personal, less promotional content
- Time decay so fresh posts still matter
- Expo React Native screen with browser preview
- Infinite scroll, loading, empty, and error states
- Unit tests for ranking, authenticity, vector formatting, and embeddings
- Raw SQL challenge answers in `sql/queries.sql`

## Repository Map

| Area | Path |
|------|------|
| Technical solution document | `docs/TSD.md` |
| Laravel API | `app/`, `routes/api.php`, `database/migrations/` |
| Feed ranking services | `app/Services/Feed/` |
| Embedding client and vector formatting | `app/Services/Embeddings/` |
| Python embedding service | `embedding/` |
| React Native screen | `mobile/src/screens/FeedScreen.tsx` |
| Mobile API client and types | `mobile/src/api/`, `mobile/src/types/` |
| SQL challenge answers | `sql/queries.sql` |
| Local runtime | `docker-compose.yml`, `Dockerfile.api`, `Makefile` |

## Configuration

### Quick Setup

Create `.env` from the template:

```bash
cp .env.example .env
```

Default ports:

```env
API_HOST_PORT=8000
POSTGRES_HOST_PORT=5432
EMBEDDING_HOST_PORT=18080
```

If `API_HOST_PORT` changes, update these values to the same port:

```env
APP_URL=http://localhost:8000
EXPO_PUBLIC_API_URL=http://localhost:8000/api
```

### Important Environment Variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `API_HOST_PORT` | `8000` | Host port for Laravel API |
| `POSTGRES_HOST_PORT` | `5432` | Host port for local PostgreSQL debugging |
| `EMBEDDING_HOST_PORT` | `18080` | Host port for local embedding docs/debugging |
| `DB_HOST` | `postgres` | Docker service name used by Laravel |
| `FEED_EMBEDDING_SERVICE_URL` | `http://embedding:8080` | Internal Docker URL for embeddings |
| `FEED_EMBEDDING_SERVICE_TOKEN` | `local-embedding-service-token` | Shared backend service token |
| `FEED_EMBEDDING_DIMENSIONS` | `384` | Vector size stored in pgvector |
| `FEED_EMBEDDING_MODEL` | `hash-embedding-v1` | Current embedding model name |
| `EXPO_PUBLIC_API_URL` | `http://localhost:8000/api` | API URL used by Expo |
| `EXPO_PUBLIC_AUTH_TOKEN` | empty | Optional token injected into Expo |

## Architecture

```text
Expo React Native / Web
        |
        v
Laravel API + Sanctum
        |
        +-- PostgreSQL + pgvector
        |
        +-- Python FastAPI embedding service
```

Request flow:

```text
1. User logs in through Laravel.
2. Laravel returns a Sanctum Bearer token.
3. Mobile sends the token to protected API endpoints.
4. New posts are scored for authenticity.
5. Laravel calls the internal embedding service for vectors.
6. Vectors are stored in PostgreSQL using pgvector.
7. Feed ranking combines relationship, authenticity, semantic similarity, and time decay.
```

The root API host redirects to the API index:

```text
http://localhost:8000 -> http://localhost:8000/api
```

Embedding docs are available for local debugging:

```text
http://localhost:18080/docs
```

The embedding service is not a public user API. Laravel calls it over the Docker
network with `X-Embedding-Service-Token`.

## Feed Ranking

The feed intentionally does not rank by likes, shares, or comment volume.

Ranking signals:

| Signal | Weight | Meaning |
|--------|-------:|---------|
| Relationship depth | `0.35` | Stronger prior interaction with the author |
| Authenticity | `0.30` | Personal, less promotional, less polished content |
| Semantic similarity | `0.25` | Match with viewer's recent interests |
| Time decay | `0.10` | Recent posts get a small freshness boost |

Plain-English algorithm:

```text
1. Load recent candidate posts.
2. Exclude the viewer's own posts.
3. Score relationship depth by author.
4. Build viewer interest vector from recent interactions.
5. Compare post vectors with the viewer interest vector.
6. Apply authenticity and time-decay scores.
7. Combine weighted signals.
8. Sort by final feed score and paginate.
```

## Vector Search And Embeddings

This project uses PostgreSQL + pgvector as the vector database.

Reasons:

- It keeps relational data and vector data in one reproducible local database.
- The reviewer does not need Pinecone, Qdrant, Weaviate, or API keys.
- Migrations create the vector column and HNSW index.
- The API contract can later move to another vector store if scale requires it.

The embedding service currently uses deterministic hash embeddings. This keeps
tests stable and avoids paid model credentials during review.

Production upgrade path:

- Replace the hash embedder behind `/v1/embed`.
- Use `sentence-transformers`, OpenAI, Gemini, Voyage, Cohere, or another model.
- Keep storing `model`, `dimensions`, and `version` with every vector.
- Re-embed old content asynchronously during model migrations.

## React Native / Browser Review

### Same Laptop Browser

```bash
cd mobile
npm install
EXPO_PUBLIC_API_URL=http://localhost:8000/api EXPO_PUBLIC_AUTH_TOKEN="$TOKEN" npm run web
```

### Mobile Browser On Same Wi-Fi

Use the laptop LAN IP instead of `localhost`:

```bash
cd mobile
npm install
EXPO_PUBLIC_API_URL=http://<your-laptop-lan-ip>:8000/api EXPO_PUBLIC_AUTH_TOKEN="$TOKEN" npm run web:lan
```

Then open the Expo Web URL shown in the terminal from the phone browser.

On Linux, you can usually find the LAN IP with:

```bash
hostname -I | awk '{print $1}'
```

### Expo Go

```bash
cd mobile
EXPO_PUBLIC_API_URL=http://<your-laptop-lan-ip>:8000/api EXPO_PUBLIC_AUTH_TOKEN="$TOKEN" npm run start
```

Scan the QR code with Expo Go.

## Development

### Docker Commands

```bash
# Start services
docker compose up -d --build

# Stop services
docker compose down

# Rebuild only the embedding service
docker compose build embedding
docker compose up -d --no-deps embedding

# View logs
docker compose logs -f --tail=100
```

### Makefile Shortcuts

```bash
make docker-up-build
make docker-ps
make migrate
make seed
make embedding-install
make test
make mobile-install
make mobile-start
```

### Run Tests

```bash
# Laravel API tests inside Docker
docker compose exec -T api php artisan test

# Python embedding service tests
(cd embedding && poetry install && poetry run pytest -q)

# Mobile TypeScript check
(cd mobile && npm run typecheck)

# Expo Web production export check
(cd mobile && npx expo export --platform web)
```

Expected current status:

```text
Laravel: 4 tests passing
Embedding: 3 tests passing
Mobile: typecheck passing
Expo Web export: passing
```

## Documentation

- `docs/TSD.md` - technical solution document and architecture decisions
- `sql/queries.sql` - raw SQL challenge answers
- `routes/api.php` - API route map
- `database/migrations/` - schema and pgvector setup
- `app/Services/Feed/` - feed ranking implementation
- `embedding/src/embedding/main.py` - embedding service contract
- `mobile/src/screens/FeedScreen.tsx` - React Native feed screen

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `localhost:8000` shows 404 | Use `http://localhost:8000/api`; root `/` redirects to `/api` |
| Expo says port `8081` is busy | Accept the suggested port, for example `8082` |
| Token command says `command not found` after `5|...` | Quote the token: `EXPO_PUBLIC_AUTH_TOKEN="$TOKEN"` |
| Phone browser cannot load feed | Use laptop LAN IP in `EXPO_PUBLIC_API_URL`, not `localhost` |
| Host `php` command not found | Run Laravel tests inside Docker with `docker compose exec -T api php artisan test` |
| Embedding docs root looks blank or 404 | Open `http://localhost:18080/docs` |
| React Native DevTools sandbox error on Linux | Non-blocking Expo tooling issue; the app can still run |
| `npm audit` reports Expo transitive warnings | Do not run `npm audit fix --force`; it may downgrade Expo |

## Submission Checklist

```bash
docker compose ps
curl http://localhost:8000/api
docker compose exec -T api php artisan test
(cd embedding && poetry install && poetry run pytest -q)
(cd mobile && npm run typecheck)
```

Optional browser build check:

```bash
(cd mobile && npx expo export --platform web)
```

## AI Tool Usage

Codex was used as the agentic engineering assistant for:

- Reading and understanding the take-home brief.
- Planning the full-stack implementation.
- Writing Laravel, Python, React Native, SQL, Docker, and documentation code.
- Checking consistency between API contracts, migrations, tests, and README.

The main architecture decisions are documented in `docs/TSD.md`.
