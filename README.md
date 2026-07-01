# Guised Up Real Connections Feed

Production-ready take-home implementation for the Guised Up Real Connections
Feed. The repository contains the technical solution document, Laravel API,
Python embedding service, React Native feed screen, migrations, tests, and raw
SQL challenge answers.

## What Is Included

| Area | Path |
|---|---|
| Technical solution document | `docs/TSD.md` |
| Laravel API | `app/`, `routes/api.php`, `database/migrations/` |
| Python embedding service | `embedding_service/` |
| React Native screen | `mobile/src/screens/FeedScreen.tsx` |
| SQL challenge | `sql/queries.sql` |
| Local runtime | `docker-compose.yml`, `Makefile`, `.env.example` |

## Architecture

```text
React Native Feed Screen
        |
        v
Laravel API + Sanctum
        |
        +--> PostgreSQL + pgvector
        |
        +--> Python embedding service
```

The feed deliberately avoids ranking by popularity. It ranks candidate posts by
authenticity, relationship depth, semantic similarity to the viewer profile, and
time decay.

## Local Setup

```bash
cp .env.example .env
docker compose up -d --build
```

Docker Desktop or the Docker daemon must be running before this command.
To avoid local port conflicts, change `API_HOST_PORT`, `POSTGRES_HOST_PORT`, or
`EMBEDDING_HOST_PORT` in `.env` before starting Docker.
If `API_HOST_PORT` changes, update `APP_URL` and `EXPO_PUBLIC_API_URL` to match.

The API runs on:

```text
http://localhost:8000/api
```

The embedding service is an internal service used by Laravel. It is bound to
`127.0.0.1` for local debugging only and `/v1/embed` requires the
`FEED_EMBEDDING_SERVICE_TOKEN` service token.

For local debugging, its docs are available on:

```text
http://localhost:18080
```

Seeded users:

| Email | Password |
|---|---|
| mithlesh@example.com | password |
| prachi@example.com | password |
| demo@example.com | password |

Create a token:

```bash
curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"mithlesh@example.com","password":"password"}'
```

Use the returned `token` as:

```text
Authorization: Bearer <token>
```

## API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/auth/login` | Issue a Sanctum token for seeded users |
| `POST` | `/api/posts` | Create a post and store its vector embedding |
| `GET` | `/api/feed?page=1` | Return ranked personalized feed, 20 per page |
| `GET` | `/api/search?q=funny travel stories` | Return top 10 semantic matches |
| `POST` | `/api/interactions` | Log `view`, `reply`, or `reaction` |

Example post:

```bash
curl -X POST http://localhost:8000/api/posts \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"text":"Unfiltered walk through Old Delhi today. Got lost, found the best chai.","image_url":"https://example.com/chai.jpg"}'
```

## React Native / Browser Preview

```bash
cd mobile
npm install
EXPO_PUBLIC_API_URL=http://localhost:8000/api EXPO_PUBLIC_AUTH_TOKEN='<token>' npm run start
```

Sanctum tokens contain `|`, so quote the token value when passing it through the
shell.

For browser review on the same laptop, run:

```bash
EXPO_PUBLIC_API_URL=http://localhost:8000/api EXPO_PUBLIC_AUTH_TOKEN='<token>' npm run web
```

For mobile browser review on the same Wi-Fi network, use the laptop LAN IP
instead of `localhost`:

```bash
EXPO_PUBLIC_API_URL=http://<your-laptop-lan-ip>:8000/api EXPO_PUBLIC_AUTH_TOKEN='<token>' npm run web:lan
```

Then open the Expo Web URL shown in the terminal from the phone browser.

The screen supports paginated feed loading, infinite scroll, inline semantic
search, loading states, empty states, error states, and reaction logging.

## Security And Vector Search Notes

The public API surface is Laravel on `API_HOST_PORT`, protected with Sanctum
Bearer tokens. The Python embedding service is not a user-facing API and does
not verify user JWTs; Laravel calls it over the Docker network with
`X-Embedding-Service-Token`.

This repo uses PostgreSQL + pgvector as the vector DB. That satisfies the
assignment requirement and keeps the reviewer setup reproducible. The embedding
service uses deterministic hash embeddings so the project works without paid
API keys. In production, the same `/v1/embed` contract can be backed by
`sentence-transformers`, OpenAI/Gemini/Voyage/Cohere embeddings, or another
embedding provider, and pgvector can later be swapped for Qdrant/Pinecone if
vector search needs to scale independently from Postgres.

## Tests

Host PHP is not required if Docker is available:

```bash
docker compose exec api php artisan test
cd embedding_service && python -m pytest -q
```

The Laravel tests cover ranking, authenticity scoring, and deterministic
embedding/vector formatting. The embedding service test covers the `/v1/embed`
contract.

## AI Tool Usage

This solution was built using Codex as the agentic engineering assistant for
assignment analysis, implementation planning, code generation, and verification.
The design keeps the AI-assisted parts explicit in `docs/TSD.md` so reviewers
can see both the decisions and the trade-offs.
