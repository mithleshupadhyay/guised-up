# Engineering Review Notes

Engineering walkthrough doc with trade-offs.

## 1. One-Line Summary

I built a production-style Real Connections Feed for Guised Up:

```text
React Native feed screen
-> Laravel API
-> PostgreSQL + pgvector
-> Python embedding service
-> ranking based on relationship, authenticity, semantic relevance, and recency
```

The product goal is intentionally different from a normal popularity feed. The
feed should not rank posts by likes, shares, or comment volume. It should prefer
authentic personal posts from people the viewer has a real connection with.

## 2. What I Was Optimizing For

I optimized for four things:

1. Reviewer reproducibility.
2. Clean product reasoning.
3. Production-shaped architecture.
4. Explicit failure handling.

That means the project can run locally without paid API keys, but the design is
not limited to mock-only components. The embedding service defaults to a local
deterministic provider, while also supporting Gemini, OpenAI, and Cohere through
environment variables.

## 3. Business Problem

Most social feeds reward whatever gets engagement. That creates a predictable
failure mode:

```text
polished content -> more engagement -> more reach -> more polished content
```

For a product like Guised Up, that is dangerous because the brand promise is
authenticity. If the feed starts behaving like every other engagement platform,
the product loses its differentiation.

This project solves a narrower but important problem:

```text
Given a viewer, rank posts by real connection and authentic relevance,
not by public popularity.
```

## 4. Core Ranking Decision

The feed score combines four signals:

| Signal | Weight | Why It Exists |
|---|---:|---|
| Relationship depth | 0.35 | People the viewer actually interacts with should matter more |
| Authenticity | 0.30 | Personal posts should outrank promotional or engagement-bait text |
| Semantic relevance | 0.25 | The feed should match the viewer's current interests |
| Time decay | 0.10 | Freshness matters, but should not dominate the feed |

The important negative decision is:

```text
Likes, shares, and raw comment volume are not ranking inputs.
```

Interactions are used to understand relationship depth, but not as a public
popularity proxy.

## 5. Architecture Choices And Trade-Offs

### Laravel API

I used Laravel because the assignment stack called for it and because it is
strong for API routing, validation, auth, migrations, queues, and database
workflows.

Trade-off:

- Laravel is not the natural home for ML code.
- The API stays focused on product logic.
- Embedding generation moves to a Python service.

Where it can fail:

- If feed computation grows too complex inside request-response paths, latency
  will increase.
- The production fix is to precompute some features, run queues separately, and
  cache viewer profiles with clear invalidation.

### PostgreSQL + pgvector

I used PostgreSQL with pgvector instead of Pinecone, Weaviate, or Qdrant.

Why:

- It is easy for a reviewer to run with Docker.
- Relational data and vectors stay in one database.
- It avoids external accounts during review.
- pgvector is good enough for this scale and assignment.

Trade-off:

- A managed vector database may scale better for very high volume semantic
  search.
- pgvector still needs careful indexing, vacuuming, and query planning as data
  grows.

Production direction:

- Keep pgvector until there is a real scale reason to move.
- Measure query latency and recall before adding a specialized vector database.

### Python Embedding Service

The API calls a separate FastAPI service for embeddings.

Why:

- Python has the strongest ML/AI ecosystem.
- The HTTP contract keeps Laravel independent of the model implementation.
- The service can switch from hash embeddings to Gemini/OpenAI/Cohere/local
  models without changing Laravel.

Trade-off:

- Another service adds network latency and operational complexity.
- The benefit is model isolation and future flexibility.

Where it can fail:

- Provider outage.
- Slow external embedding API.
- Wrong vector dimensions returned by a provider.
- Provider model migration changes vector behavior.

What I added:

- Service token auth.
- Timeout handling.
- Hash fallback option.
- Provider selection by environment.
- Dimension validation so a provider cannot return a 3072-vector when the
  database expects 384.

### React Native Screen

The frontend is a single Expo React Native feed screen with web preview support.

Why:

- The assignment required React Native.
- Expo Web makes review easy in a browser.
- The screen exercises real API calls rather than static mock data.

Trade-off:

- It is intentionally one screen, not a full app shell.
- Auth is injected by environment for reviewer speed instead of building a full
  login UI.

Production direction:

- Add login/onboarding.
- Add native build testing.
- Add image upload flow.
- Add deeper empty, offline, retry, and refresh states.

## 6. AI And Embedding Provider Strategy

The project supports these embedding providers:

| Provider | Purpose |
|---|---|
| `hash` | Default no-key deterministic mode for review and tests |
| `gemini` | Free-tier realistic embedding test path |
| `openai` | Production-grade hosted embedding option |
| `cohere` | Hosted embedding option with a 384-dimensional light model |

I did not use Groq for embeddings because Groq is mainly useful for fast
LLM/chat inference. This feature needs embedding vectors, not chat completions.

Important trade-off:

- Hash embeddings are not semantically as good as model embeddings.
- They make tests reproducible and setup easy.
- Real embeddings can be enabled with environment variables.

In my local validation, Gemini was enabled and produced correct 384-dimensional
vectors stored in pgvector.

## 7. Failure Modes I Would Expect

### 1. Authenticity Scoring Can Be Gamed

The current authenticity scorer is heuristic. It looks for signals such as
personal language, promotional tone, excessive hashtags, and polished ad-like
copy.

Where it can fail:

- Users may learn how to write around the heuristic.
- Short posts may be scored incorrectly.
- Some genuine creators may naturally write in a polished style.

Production fix:

- Add human-labeled evaluation data.
- Use model-assisted classification with confidence scores.
- Keep the score explainable enough for debugging.
- Avoid making authenticity a single absolute truth.

### 2. Retrieval Can Return Irrelevant Posts

Semantic search can fail when embeddings are weak, query text is vague, or
viewer history is sparse.

Production fix:

- Track precision@k on test queries.
- Add hybrid search: vector similarity plus keyword filters.
- Use reranking for high-value queries.
- Add thresholds so low-confidence matches do not dominate the feed.

### 3. New Users Have Sparse Profiles

A new viewer may have no interactions, so relationship and semantic signals are
weak.

Production fix:

- Use onboarding interests.
- Use location/language/community signals if available.
- Start with freshness and quality signals.
- Gradually personalize as interactions accumulate.

### 4. Provider Embeddings Can Drift

If the embedding model changes, old vectors and new vectors may not be
comparable.

Production fix:

- Store model, dimensions, and version with every embedding.
- Re-embed content asynchronously during model migration.
- Never mix incompatible vector spaces in the same ranking query.

### 5. Caches Can Become Stale

Viewer interest profiles and relationship scores are cached.

Trade-off:

- Caching reduces feed latency.
- Stale cache can temporarily rank the wrong posts.

Production fix:

- Short TTLs for volatile signals.
- Explicit invalidation on new interactions.
- Monitor cache hit rates and feed latency.

### 6. Queue Execution Needs Real Workers

Local setup uses `QUEUE_CONNECTION=sync` for simplicity.

Trade-off:

- Simple review setup.
- Not production-grade for heavy embedding workloads.

Production fix:

- Use Redis or database queues.
- Run dedicated queue workers.
- Add retry, dead-letter handling, and alerts.

## 8. How I Would Evaluate This System

I would not evaluate this only by "does the feed load".

I would evaluate:

| Area | Evaluation |
|---|---|
| Ranking quality | Human-labeled feed order comparisons |
| Semantic search | Precision@k and recall@k on known query/post pairs |
| Authenticity | Confusion matrix on labeled authentic/promotional posts |
| Latency | p50/p95 feed, search, post creation, embedding latency |
| Reliability | Provider timeout rate, fallback rate, queue failure count |
| Safety | Rate-limit events, auth failures, suspicious write patterns |

The strongest signal would be a small internal evaluation set:

```text
viewer profile + candidate posts + expected ranking notes
```

That lets the team compare ranking changes before shipping them.

## 9. What I Would Monitor After Deployment

Minimum production telemetry:

- Request count, latency, and error rate per endpoint.
- Feed p95 latency.
- Embedding provider latency and error rate.
- Queue depth and failed jobs.
- Number of posts waiting for embeddings.
- Vector dimension/model mismatch errors.
- Search zero-result rate.
- Cache hit/miss rate.
- Rate-limit rejections.

This project already includes request IDs, logs, rate limits, and a protected
metrics endpoint to show the intended direction.

## 10. Security And Privacy Notes

Current implementation:

- Sanctum Bearer tokens protect user APIs.
- Embedding service uses an internal service token.
- `.env` is ignored and `.env.example` contains no real secrets.
- Write and auth routes are rate-limited.

Production additions:

- HTTPS everywhere.
- Secret manager instead of local env files.
- Short-lived tokens or refresh-token flow.
- Audit logs for sensitive actions.
- Stronger abuse detection for post creation and interactions.

## 11. What I Would Change With More Time

If this were moving into production, I would prioritize:

1. A real evaluation dataset for ranking and authenticity.
2. Dedicated queue workers for embedding generation.
3. Redis for cache and queue infrastructure.
4. Hybrid semantic search with thresholds and reranking.
5. Image analysis for filter/beauty/screenshot detection.
6. More complete auth UX in the React Native app.
7. Observability dashboards and alerts.
8. Load tests with larger post and interaction volumes.
9. Admin tools to inspect why a post ranked where it did.
10. CI that runs Laravel, Python, and mobile checks on every pull request.

## 12. Video Walkthrough Outline

This is the recording flow I would use:

1. State the product problem.

```text
This is a social feed that intentionally avoids popularity ranking. The goal is
to surface authentic posts from real relationships.
```

2. Show the architecture.

```text
React Native calls Laravel. Laravel uses PostgreSQL and pgvector. Embeddings are
handled by a separate Python service.
```

3. Show the running app.

```text
Open Expo Web, show feed, search, and reaction.
```

4. Show backend validation.

```text
docker compose ps
curl http://localhost:8000/api
curl http://localhost:18080/health
```

5. Explain the ranking formula.

```text
Relationship, authenticity, semantic similarity, and recency. No popularity
count ranking.
```

6. Explain one real trade-off.

```text
I used pgvector for reproducible local review. At much larger scale I would
measure pgvector first, then consider a dedicated vector database if recall or
latency required it.
```

7. Explain one failure mode.

```text
Authenticity scoring is heuristic and can be gamed. In production I would add a
labeled evaluation set, confidence thresholds, model-assisted scoring, and human
review for edge cases.
```

8. End with verification.

```text
Laravel tests pass, embedding tests pass, mobile typecheck passes, and Gemini
provider mode was validated with 384-dimensional vectors.
```

## 13. Final Engineering Position

This solution is intentionally not presented as perfect.

It is a production-shaped take-home with clear boundaries:

- API logic in Laravel.
- ML/model logic in Python.
- Vector search in pgvector.
- Review-safe local defaults.
- Provider-based embedding upgrades.
- Explicit fallbacks and validation.

The main thing I would emphasize in a senior review is:

```text
The architecture is useful because it makes trade-offs visible.
The system can run simply today, but it has a path to production hardening
without rewriting the whole project.
```
