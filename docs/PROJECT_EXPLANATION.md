# Guised Up Project Explanation

This document explains the project in simple language: what it is, what problem
it solves, why it is designed this way, and how the main parts work together.

## 1. What Is This Project?

This project is a small full-stack version of a social feed for Guised Up.

Think of it like an Instagram or social app feed, but with one important
difference:

```text
The feed should promote real, authentic, personal posts.
It should not promote posts only because they got many likes, comments, or shares.
```

So the project builds a "Real Connections Feed".

The app shows posts that are likely to feel honest and personally relevant to
the user.

## 2. What Business Problem Does It Solve?

Most social feeds reward popularity.

That means the feed often shows:

- highly polished posts
- viral posts
- engagement bait
- promotional captions
- content from people who are good at getting attention

But Guised Up wants a different type of feed.

The business goal is:

```text
Help users see more genuine posts from people they actually care about.
```

This matters because a product focused on authenticity needs trust. If the feed
feels fake, promotional, or popularity-driven, users may stop believing the app
is different from other social platforms.

This project solves that by ranking posts using signals that match the product
goal:

- Is the post authentic?
- Does the viewer have a real relationship with the author?
- Is the post semantically relevant to the viewer?
- Is the post recent enough to still matter?

## 3. Simple Example

Suppose there are two posts.

Post A:

```text
LIMITED OFFER follow for more perfect travel edits!!! #viral #goals
```

Post B:

```text
Got lost in Old Delhi today, found a quiet chai shop, and it made the day better.
```

A normal popularity feed might prefer Post A if it gets more likes.

This project should prefer Post B if it is more personal, more authentic, and
comes from someone the viewer has interacted with before.

## 4. What Does The Project Contain?

The project has three main parts:

| Part | Technology | Purpose |
|------|------------|---------|
| Backend API | Laravel | Login, posts, feed ranking, search, interactions |
| Database | PostgreSQL + pgvector | Store users, posts, interactions, and vectors |
| Embedding service | Python FastAPI | Convert text into vectors for semantic search |
| Frontend | Expo React Native | Show the feed in browser or mobile preview |

## 5. How The User Experience Works

User flow:

```text
1. User opens the feed screen.
2. Mobile app calls Laravel API.
3. Laravel checks the user's Bearer token.
4. Laravel loads candidate posts from PostgreSQL.
5. Laravel scores each post.
6. Laravel returns the best posts first.
7. Mobile app displays the ranked feed.
```

The reviewer can open the browser preview and see:

- ranked feed posts
- authenticity score
- final feed score
- search bar
- reaction button
- loading and error states

## 6. How Feed Ranking Works

The feed score is built from four signals.

| Signal | Why It Matters |
|--------|----------------|
| Relationship depth | Posts from people the viewer interacts with should matter more |
| Authenticity | Personal and natural posts should rank higher than promotional posts |
| Semantic similarity | Posts related to viewer interests should rank higher |
| Time decay | Fresh posts should have a small advantage |

The rough formula is:

```text
feed_score =
    relationship_score * 0.35
  + authenticity_score * 0.30
  + semantic_score * 0.25
  + time_decay_score * 0.10
```

Important:

```text
Likes, shares, and comment volume are not used as popularity ranking signals.
```

This is intentional because the business requirement is to avoid popularity
ranking.

## 7. What Is An Embedding?

An embedding is a list of numbers that represents meaning.

Example:

```text
"funny travel story"
```

gets converted into something like:

```text
[0.12, -0.03, 0.44, ...]
```

The exact numbers are not important for humans. The important idea is:

```text
Similar text gets similar vectors.
```

That lets the system search by meaning instead of exact keywords.

For example:

```text
Search: "funny travel stories"
```

can match posts about:

- getting lost during a trip
- an unexpected travel moment
- a personal travel mistake

even if the post does not contain the exact words "funny travel stories".

## 8. Why PostgreSQL + pgvector?

The project uses PostgreSQL with pgvector.

PostgreSQL stores normal data:

- users
- posts
- interactions
- tokens

pgvector stores vector data:

- post embeddings
- searchable meaning representation

Why this is a good choice for this take-home:

- easy to run locally with Docker
- no paid API keys required
- no external vector database account required
- reviewer can reproduce everything quickly
- production can later move to Pinecone, Qdrant, or another vector DB if needed

## 9. Why A Separate Python Embedding Service?

Laravel handles the product API.

Python handles embeddings.

This is useful because Python has the strongest ecosystem for ML and AI models.

Current project:

```text
Laravel API -> Python embedding service -> vector returned -> PostgreSQL
```

In this submission, the embedding service uses deterministic hash embeddings.

That means:

- it works without OpenAI/Gemini/Hugging Face keys
- tests are stable
- reviewer setup is simple
- the service contract is still production-like

In production, the inside of the embedding service can be replaced with:

- sentence-transformers
- OpenAI embeddings
- Gemini embeddings
- Voyage embeddings
- Cohere embeddings

The Laravel API does not need to change if the `/v1/embed` contract stays the
same.

## 10. Why Laravel Sanctum?

The API is protected with Laravel Sanctum Bearer tokens.

Flow:

```text
1. User logs in with email and password.
2. Laravel returns a token.
3. Mobile app sends the token with each protected request.
4. Laravel knows which user is asking for the feed.
```

This is needed because each user should get a different personalized feed.

## 11. Why The Embedding Service Does Not Use User JWT?

The embedding service is not called by users.

Only Laravel calls it.

So the embedding service uses a backend service token:

```text
X-Embedding-Service-Token
```

This keeps responsibility clear:

```text
User authentication: Laravel Sanctum
Service-to-service authentication: shared embedding service token
```

## 12. What Happens When A Post Is Created?

Flow:

```text
1. User creates a post.
2. Laravel validates the request.
3. Laravel calculates authenticity score.
4. Laravel sends post text to Python embedding service.
5. Python returns a vector.
6. Laravel stores post and vector in PostgreSQL.
7. The post can now appear in feed and semantic search.
```

## 13. What Happens When Feed Is Loaded?

Flow:

```text
1. User opens feed.
2. Laravel loads recent posts.
3. Laravel loads viewer interaction history.
4. Laravel builds viewer interest profile.
5. Laravel compares post vectors with viewer interest vector.
6. Laravel combines all ranking signals.
7. Laravel returns paginated feed results.
```

## 14. What Happens When Search Is Used?

Flow:

```text
1. User types search query.
2. Laravel sends query text to embedding service.
3. Embedding service returns query vector.
4. PostgreSQL pgvector compares query vector with post vectors.
5. API returns the most semantically similar posts.
```

## 15. Why Docker Is Used

Docker makes the project easy to run.

Without Docker, the reviewer would need to install and configure:

- PHP
- Composer
- PostgreSQL
- pgvector
- Python service
- environment variables

With Docker:

```bash
cp .env.example .env
docker compose up -d --build
```

starts the backend services in a repeatable way.

## 16. Why Expo Web Is Used

The assignment asks for a React Native screen.

React Native normally runs on a phone simulator or Expo Go, but reviewers often
prefer a browser preview.

Expo Web gives both options:

```text
React Native code -> browser preview
React Native code -> mobile preview
```

This makes the project easier to review.

## 17. What Is Production-Ready About This?

This is still a take-home project, but it uses production-style boundaries.

Good engineering choices included:

- separate API, database, embedding service, and mobile app
- Dockerized backend setup
- environment-based configuration
- token-based user authentication
- service-to-service token for internal embedding API
- pgvector migration and vector formatting
- test coverage for ranking and embedding logic
- clear README and technical solution document
- no required paid API keys

## 18. What Was Added To Make It More Production-Ready?

The project now includes production-style improvements that still work locally
without external paid services:

- Queue-based post embedding generation with Laravel jobs.
- Cached viewer interest vectors and relationship scores.
- Rate limits for login, read APIs, and write APIs.
- Request ID middleware for traceable API logs.
- A protected `/api/metrics` endpoint for operational counters.
- Optional OpenAI-compatible embedding provider support.
- Hash embeddings remain the default so reviewers do not need API keys.

This makes the code closer to a production architecture while keeping local
setup simple.

## 19. What Would Still Improve In Real Production?

Next production steps:

- run a real queue worker separately from the API container
- use managed Redis for cache and queues
- use real embedding credentials in the embedding service
- add stronger image analysis for real filter detection
- add pagination and ranking performance tests with larger data
- add dashboards and alerts around the metrics endpoint
- deploy API, database, embedding service, queue worker, and frontend separately

## 20. One-Line Summary

```text
This project builds a social feed that ranks posts by authenticity,
relationships, semantic relevance, and freshness instead of popularity.
```

## 21. Short Interview Explanation

You can explain it like this:

```text
I built a full-stack Real Connections Feed for Guised Up. The main business
problem is that normal social feeds reward popularity and polished content,
while Guised Up wants users to see more authentic posts from people they care
about. I solved this with a Laravel API, PostgreSQL with pgvector, a Python
embedding service, and an Expo React Native feed screen. The feed ranks posts
using relationship depth, authenticity, semantic similarity, and time decay,
not likes or comment volume. The project is Dockerized, uses Sanctum auth, has
service-to-service protection for embeddings, queues embedding generation,
caches feed features, exposes basic metrics, and includes tests and SQL answers
for review.
```
