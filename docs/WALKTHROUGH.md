# Reviewer Runbook

This document helps a reviewer run and verify the Guised Up take-home project
from a clean local state.

The project implements a Real Connections Feed using:

- Laravel API for authentication, feed, search, posts, interactions, and metrics
- PostgreSQL with pgvector for relational and vector storage
- Python FastAPI embedding service with hash, Gemini, OpenAI, and Cohere modes
- Expo React Native feed screen that can run in the browser

The feed ranking intentionally avoids public popularity signals such as raw
likes, shares, and comment volume. It uses relationship signals, authenticity,
semantic relevance, and recency.

## Safety Notes

Do not commit or share:

- `.env`
- API keys
- bearer tokens
- private browser tabs
- email inbox screenshots

The repository should contain `.env.example`, not local private `.env` values.

## 1. Start From Project Root

```bash
cd /home/rudra/Desktop/assignment/guised-up
pwd
ls
ls docs
```

Useful docs:

- `README.md`
- `docs/TSD.md`
- `docs/PROJECT_EXPLANATION.md`
- `docs/ENGINEERING_REVIEW.md`
- `docs/WALKTHROUGH.md`

## 2. Confirm Secret Safety

```bash
git status --short
git check-ignore .env && echo ".env is ignored"
```

Optional tracked-secret scan:

```bash
python3 - <<'PY'
import pathlib
import re
import subprocess

files = subprocess.check_output(["git", "ls-files"], text=True).splitlines()
secret = re.compile(r"AIza[0-9A-Za-z_-]{20,}")
matches = []

for file_name in files:
    path = pathlib.Path(file_name)
    try:
        text = path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        continue

    if secret.search(text):
        matches.append(file_name)

if matches:
    print("Potential tracked API key:", matches)
    raise SystemExit(1)

print("No tracked Gemini-looking API keys detected")
PY
```

Expected result:

- `.env is ignored`
- no tracked Gemini-looking API keys

## 3. Clean Local Runtime

This removes containers, SQL data, Docker volumes, and generated local caches
for this project. It does not delete source code.

```bash
docker compose down -v --remove-orphans
rm -rf mobile/.expo mobile/dist embedding/.pytest_cache .phpunit.result.cache
```

## 4. Build And Start Services

```bash
test -f .env || cp .env.example .env
docker compose build --no-cache
docker compose up -d
docker compose ps
```

Expected services:

```text
guised-up-api-1
guised-up-embedding-1
guised-up-postgres-1
```

What this validates:

- Laravel API container builds and starts
- PostgreSQL with pgvector starts
- Python embedding service starts and passes health checks

## 5. Reset SQL Schema And Insert Initial Data

The API container runs migrations and seeders on startup. These commands make
the reset explicit and reproducible.

```bash
docker compose exec -T api php artisan optimize:clear
docker compose exec -T api php artisan migrate:fresh --seed --force
```

Verify seeded records:

```bash
docker compose exec -T postgres psql -U guised_up -d guised_up -Atc \
  "select 'users=' || count(*) from users;
   select 'posts=' || count(*) from posts;
   select 'post_embeddings=' || count(*) from post_embeddings;
   select 'interactions=' || count(*) from interactions;"
```

Expected shape:

```text
users=3
posts=4
post_embeddings=4
interactions=3
```

## 6. Verify Backend Health

Laravel API:

```bash
curl -s http://localhost:8000/api | python3 -m json.tool
```

Embedding service:

```bash
curl -s http://localhost:18080/health | python3 -m json.tool
```

pgvector extension:

```bash
docker compose exec -T postgres psql -U guised_up -d guised_up -c "\dx vector"
```

Direct embedding call:

```bash
SERVICE_TOKEN=$(grep '^FEED_EMBEDDING_SERVICE_TOKEN=' .env | cut -d= -f2-)

curl -s -X POST http://localhost:18080/v1/embed \
  -H "Content-Type: application/json" \
  -H "X-Embedding-Service-Token: $SERVICE_TOKEN" \
  -d '{"texts":["funny honest travel story from last week"],"dimensions":384}' \
  | python3 -c 'import json,sys; b=json.load(sys.stdin); v=b["embeddings"][0]["vector"]; print({"model": b["model"], "reported_dimensions": b["dimensions"], "actual_dimensions": len(v), "norm": round(sum(x*x for x in v)**0.5, 4)})'
```

Expected shape:

```text
{'model': '<model-name>', 'reported_dimensions': 384, 'actual_dimensions': 384, 'norm': 1.0}
```

## 7. Authenticate Seeded User

The token is stored in a shell variable and is not printed.

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"mithlesh@example.com","password":"password"}' \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["token"])')

test -n "$TOKEN" && echo "login ok"
```

Verify the authenticated user:

```bash
curl -s http://localhost:8000/api/me \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -m json.tool
```

## 8. Verify Feed API

```bash
curl -s "http://localhost:8000/api/feed?page=1" \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -c 'import json,sys; b=json.load(sys.stdin); print({"items": len(b["data"]), "first_post_id": b["data"][0]["id"] if b["data"] else None})'
```

Optional full response:

```bash
curl -s "http://localhost:8000/api/feed?page=1" \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -m json.tool
```

What this validates:

- authenticated feed access
- paginated feed response
- personalized ranking pipeline

## 9. Verify Semantic Search

```bash
curl -s "http://localhost:8000/api/search?q=funny%20travel%20stories" \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -c 'import json,sys; b=json.load(sys.stdin); print({"items": len(b["data"]), "top_post_id": b["data"][0]["id"] if b["data"] else None})'
```

What this validates:

- query embedding
- pgvector-backed semantic matching
- authenticated search endpoint

## 10. Create Post And Verify Embedding Storage

Create a post through the API:

```bash
POST_RESPONSE=$(curl -s -X POST http://localhost:8000/api/posts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text":"Fresh rebuild validation: a real train ride, chai, rain, and a small honest moment.","image_url":"https://example.com/chai.jpg"}')

echo "$POST_RESPONSE" \
  | python3 -c 'import json,sys; b=json.load(sys.stdin)["data"]; print({"post_id": b["id"], "authenticity_score": b["authenticity_score"]})'

POST_ID=$(echo "$POST_RESPONSE" \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["id"])')
```

Verify stored vector metadata:

```bash
docker compose exec -T postgres psql -U guised_up -d guised_up -Atc \
  "select model, dimensions, vector_dims(embedding)
   from post_embeddings
   where post_id = $POST_ID;"
```

Expected shape:

```text
<model-name>|384|384
```

What this validates:

- authenticated post creation
- authenticity scoring
- embedding generation
- pgvector storage

## 11. Verify Interaction Logging

```bash
curl -s -X POST http://localhost:8000/api/interactions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"post_id\":$POST_ID,\"type\":\"reaction\",\"metadata\":{\"reaction\":\"heart\"}}" \
  | python3 -m json.tool
```

What this validates:

- interaction write path
- relationship-signal data collection
- no dependency on public popularity counters for ranking

## 12. Verify Metrics

```bash
curl -s http://localhost:8000/api/metrics \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -c 'import json,sys; b=json.load(sys.stdin); print({"users": b["feed"]["users"], "posts": b["feed"]["posts"], "post_embeddings": b["feed"]["post_embeddings"], "pending_jobs": b["queues"]["pending_jobs"], "embedding_model": b["runtime"]["embedding_model"]})'
```

What this validates:

- protected metrics endpoint
- feed data counters
- queue state visibility
- active embedding model visibility

## 13. Optional: Add More Demo Users And Posts

There is no public registration endpoint in this take-home API. For richer demo
data, insert users as seed-style data and create posts through `/api/posts` so
the normal post pipeline still runs.

These users and posts are local runtime data only. To make them permanent,
move them into `database/seeders/DatabaseSeeder.php`.

Create extra users:

```bash
PASSWORD_HASH=$(docker compose exec -T api php -r 'echo password_hash("password", PASSWORD_BCRYPT);')

docker compose exec -T postgres psql -U guised_up -d guised_up <<SQL
insert into users (name, username, email, password, email_verified_at, created_at, updated_at)
values
  ('Aarav Mehta', 'aarav', 'aarav@example.com', '$PASSWORD_HASH', now(), now(), now()),
  ('Naina Rao', 'naina', 'naina@example.com', '$PASSWORD_HASH', now(), now(), now())
on conflict (email) do update
set
  name = excluded.name,
  username = excluded.username,
  password = excluded.password,
  email_verified_at = excluded.email_verified_at,
  updated_at = now();
SQL
```

Login as the new users without printing tokens:

```bash
AARAV_TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"aarav@example.com","password":"password"}' \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["token"])')

NAINA_TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"naina@example.com","password":"password"}' \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["token"])')

test -n "$AARAV_TOKEN" && test -n "$NAINA_TOKEN" && echo "extra user logins ok"
```

Create posts through the API:

```bash
AARAV_POST=$(curl -s -X POST http://localhost:8000/api/posts \
  -H "Authorization: Bearer $AARAV_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text":"Missed my stop today and found a tiny breakfast place near the old station. Not planned, but worth remembering.","image_url":"https://example.com/station-breakfast.jpg"}')

NAINA_POST=$(curl -s -X POST http://localhost:8000/api/posts \
  -H "Authorization: Bearer $NAINA_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text":"No big update. Just a quiet evening, wet roads, and a song I had forgotten about.","image_url":"https://example.com/rain-evening.jpg"}')

AARAV_POST_ID=$(echo "$AARAV_POST" \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["id"])')

NAINA_POST_ID=$(echo "$NAINA_POST" \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["id"])')

echo "created extra posts: $AARAV_POST_ID $NAINA_POST_ID"
```

Create relationship activity from the main seeded user:

```bash
curl -s -X POST http://localhost:8000/api/interactions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"post_id\":$AARAV_POST_ID,\"type\":\"reaction\",\"metadata\":{\"reaction\":\"heart\"}}" \
  | python3 -m json.tool

curl -s -X POST http://localhost:8000/api/interactions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"post_id\":$NAINA_POST_ID,\"type\":\"reply\",\"metadata\":{\"text\":\"This felt real.\"}}" \
  | python3 -m json.tool
```

Verify the new posts have stored embeddings:

```bash
docker compose exec -T postgres psql -U guised_up -d guised_up -Atc \
  "select posts.id, users.username, post_embeddings.model, post_embeddings.dimensions, vector_dims(post_embeddings.embedding)
   from posts
   join users on users.id = posts.author_id
   join post_embeddings on post_embeddings.post_id = posts.id
   where posts.id in ($AARAV_POST_ID, $NAINA_POST_ID)
   order by posts.id;"
```

Verify the feed has more data:

```bash
curl -s "http://localhost:8000/api/feed?page=1" \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -c 'import json,sys; b=json.load(sys.stdin); print({"feed_items": len(b["data"]), "top_ids": [item["id"] for item in b["data"][:5]]})'
```

## 14. Run Tests

Laravel API tests:

```bash
docker compose exec -T api php artisan test
```

Python embedding service checks:

```bash
cd /home/rudra/Desktop/assignment/guised-up/embedding
poetry install
poetry run ruff check src tests
poetry run pytest -q
```

Mobile typecheck:

```bash
cd /home/rudra/Desktop/assignment/guised-up/mobile
npm install
npm run typecheck
```

Optional Expo web export:

```bash
npx expo export --platform web
```

## 15. Run React Native Web Screen

Start Expo Web:

```bash
cd /home/rudra/Desktop/assignment/guised-up/mobile
EXPO_PUBLIC_API_URL=http://localhost:8000/api EXPO_PUBLIC_AUTH_TOKEN="$TOKEN" npm run web
```

Open the printed web URL:

```text
http://localhost:8081
```

If Expo asks to use `8082`, accept it and open the printed URL.

UI checks:

1. Feed loads from the Laravel API.
2. Posts show author, text, authenticity score, and final feed score.
3. Search works with a query such as `chai rain train`.
4. Reaction button writes an interaction.
5. Refresh reloads current API data.

## 16. Architecture Review Points

Use `docs/TSD.md` and `docs/ENGINEERING_REVIEW.md` for the design explanation.

Key points:

- Laravel owns product APIs, auth, ranking orchestration, and validation.
- PostgreSQL stores relational feed data.
- pgvector stores post embeddings and supports semantic retrieval.
- Python FastAPI owns embedding provider logic.
- The mobile app is an Expo React Native screen backed by the real API.
- Default local mode works without paid API keys.
- Gemini, OpenAI, and Cohere providers can be enabled through environment
  variables.

## 17. Ranking Review Points

The feed combines:

- relationship depth
- authenticity score
- semantic relevance
- time decay

The ranking intentionally does not use:

- raw like count
- raw share count
- raw comment count

This matches the assignment goal of reducing engagement-farming incentives.

## 18. Production Trade-Offs

Important trade-offs documented in `docs/ENGINEERING_REVIEW.md`:

- pgvector keeps local setup simple and reproducible, but a dedicated vector DB
  may be useful at larger scale.
- A separate Python embedding service adds one service to operate, but keeps AI
  provider logic isolated from Laravel.
- Hash embeddings keep reviewer setup keyless, while Gemini/OpenAI/Cohere modes
  allow stronger semantic quality when keys are available.
- Authenticity scoring is heuristic and should be evaluated with labeled data
  before production use.
- Semantic search should be measured with precision@k, recall@k, and hybrid
  keyword plus vector search experiments.

## 19. Useful Short Command Set

Use this when the reviewer wants a quick health check instead of a full reset.

```bash
cd /home/rudra/Desktop/assignment/guised-up
docker compose up -d --build
docker compose ps
curl -s http://localhost:8000/api | python3 -m json.tool
curl -s http://localhost:18080/health | python3 -m json.tool
docker compose exec -T api php artisan test
(cd embedding && poetry run pytest -q)
(cd mobile && npm run typecheck)
```

## 20. Full Command Set

Use this for a full local rebuild and verification pass.

```bash
cd /home/rudra/Desktop/assignment/guised-up

git status --short
git check-ignore .env && echo ".env is ignored"

docker compose down -v --remove-orphans
rm -rf mobile/.expo mobile/dist embedding/.pytest_cache .phpunit.result.cache

test -f .env || cp .env.example .env
docker compose build --no-cache
docker compose up -d
docker compose ps

docker compose exec -T api php artisan optimize:clear
docker compose exec -T api php artisan migrate:fresh --seed --force

curl -s http://localhost:8000/api | python3 -m json.tool
curl -s http://localhost:18080/health | python3 -m json.tool

docker compose exec -T postgres psql -U guised_up -d guised_up -c "\dx vector"
docker compose exec -T postgres psql -U guised_up -d guised_up -Atc \
  "select 'users=' || count(*) from users;
   select 'posts=' || count(*) from posts;
   select 'post_embeddings=' || count(*) from post_embeddings;
   select 'interactions=' || count(*) from interactions;"

SERVICE_TOKEN=$(grep '^FEED_EMBEDDING_SERVICE_TOKEN=' .env | cut -d= -f2-)
curl -s -X POST http://localhost:18080/v1/embed \
  -H "Content-Type: application/json" \
  -H "X-Embedding-Service-Token: $SERVICE_TOKEN" \
  -d '{"texts":["funny honest travel story from last week"],"dimensions":384}' \
  | python3 -c 'import json,sys; b=json.load(sys.stdin); v=b["embeddings"][0]["vector"]; print({"model": b["model"], "reported_dimensions": b["dimensions"], "actual_dimensions": len(v), "norm": round(sum(x*x for x in v)**0.5, 4)})'

TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"mithlesh@example.com","password":"password"}' \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["token"])')
test -n "$TOKEN" && echo "login ok"

curl -s http://localhost:8000/api/me \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -m json.tool

curl -s "http://localhost:8000/api/feed?page=1" \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -c 'import json,sys; b=json.load(sys.stdin); print({"items": len(b["data"]), "first_post_id": b["data"][0]["id"] if b["data"] else None})'

curl -s "http://localhost:8000/api/search?q=funny%20travel%20stories" \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -c 'import json,sys; b=json.load(sys.stdin); print({"items": len(b["data"]), "top_post_id": b["data"][0]["id"] if b["data"] else None})'

POST_RESPONSE=$(curl -s -X POST http://localhost:8000/api/posts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"text":"Fresh rebuild validation: a real train ride, chai, rain, and a small honest moment.","image_url":"https://example.com/chai.jpg"}')

echo "$POST_RESPONSE" \
  | python3 -c 'import json,sys; b=json.load(sys.stdin)["data"]; print({"post_id": b["id"], "authenticity_score": b["authenticity_score"]})'

POST_ID=$(echo "$POST_RESPONSE" \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["id"])')

docker compose exec -T postgres psql -U guised_up -d guised_up -Atc \
  "select model, dimensions, vector_dims(embedding)
   from post_embeddings
   where post_id = $POST_ID;"

curl -s -X POST http://localhost:8000/api/interactions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"post_id\":$POST_ID,\"type\":\"reaction\",\"metadata\":{\"reaction\":\"heart\"}}" \
  | python3 -m json.tool

curl -s http://localhost:8000/api/metrics \
  -H "Authorization: Bearer $TOKEN" \
  | python3 -c 'import json,sys; b=json.load(sys.stdin); print({"users": b["feed"]["users"], "posts": b["feed"]["posts"], "post_embeddings": b["feed"]["post_embeddings"], "pending_jobs": b["queues"]["pending_jobs"], "embedding_model": b["runtime"]["embedding_model"]})'

docker compose exec -T api php artisan test

cd /home/rudra/Desktop/assignment/guised-up/embedding
poetry install
poetry run ruff check src tests
poetry run pytest -q

cd /home/rudra/Desktop/assignment/guised-up/mobile
npm install
npm run typecheck
EXPO_PUBLIC_API_URL=http://localhost:8000/api EXPO_PUBLIC_AUTH_TOKEN="$TOKEN" npm run web
```

## 21. Operational Commands

View logs:

```bash
cd /home/rudra/Desktop/assignment/guised-up
docker compose logs -f --tail=100
```

Stop services without deleting volumes:

```bash
cd /home/rudra/Desktop/assignment/guised-up
docker compose down
```

Rebuild only the embedding service:

```bash
cd /home/rudra/Desktop/assignment/guised-up
docker compose build embedding
docker compose up -d --no-deps embedding
curl -s http://localhost:18080/health | python3 -m json.tool
```

Run queue worker manually:

```bash
docker compose exec api php artisan queue:work --queue=embeddings,default
```

Local default is `QUEUE_CONNECTION=sync`, so a queue worker is not required for
the default Docker run.

Makefile shortcuts:

```bash
make docker-up-build
make docker-ps
make migrate
make seed
make test
make embedding-install
make mobile-install
make mobile-start
```

## 22. Mobile Browser On Same Wi-Fi

Get laptop LAN IP:

```bash
cd /home/rudra/Desktop/assignment/guised-up
hostname -I | awk '{print $1}'
```

Start Expo Web with LAN API URL:

```bash
cd /home/rudra/Desktop/assignment/guised-up/mobile
EXPO_PUBLIC_API_URL=http://<your-laptop-lan-ip>:8000/api EXPO_PUBLIC_AUTH_TOKEN="$TOKEN" npm run web:lan
```

Open the Expo Web URL printed by the terminal on the phone browser.

## 23. Expo Go

Use this if native Expo Go testing is needed:

```bash
cd /home/rudra/Desktop/assignment/guised-up/mobile
EXPO_PUBLIC_API_URL=http://<your-laptop-lan-ip>:8000/api EXPO_PUBLIC_AUTH_TOKEN="$TOKEN" npm run start
```

Scan the QR code with Expo Go.

## 24. Browser Export Check

```bash
cd /home/rudra/Desktop/assignment/guised-up/mobile
npx expo export --platform web
```
