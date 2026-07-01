from fastapi.testclient import TestClient

from guised_up_embeddings.main import app, hash_embed


def test_hash_embed_is_deterministic_and_normalized() -> None:
    first = hash_embed("funny travel story from last week", 32)
    second = hash_embed("funny travel story from last week", 32)

    assert first == second
    assert len(first) == 32
    assert abs(sum(value * value for value in first) ** 0.5 - 1.0) < 0.000001


def test_embed_endpoint_returns_vectors() -> None:
    client = TestClient(app)

    response = client.post(
        "/v1/embed",
        json={"texts": ["messy honest travel story"], "dimensions": 16},
    )

    assert response.status_code == 200
    body = response.json()
    assert body["dimensions"] == 16
    assert body["embeddings"][0]["index"] == 0
    assert len(body["embeddings"][0]["vector"]) == 16


def test_embed_endpoint_requires_service_token_when_configured(monkeypatch) -> None:
    monkeypatch.setenv("EMBEDDING_SERVICE_TOKEN", "service-secret")
    client = TestClient(app)

    unauthenticated = client.post(
        "/v1/embed",
        json={"texts": ["messy honest travel story"], "dimensions": 16},
    )
    authenticated = client.post(
        "/v1/embed",
        headers={"X-Embedding-Service-Token": "service-secret"},
        json={"texts": ["messy honest travel story"], "dimensions": 16},
    )

    assert unauthenticated.status_code == 401
    assert authenticated.status_code == 200
