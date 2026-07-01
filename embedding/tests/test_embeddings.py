import pytest
from fastapi import HTTPException
from fastapi.testclient import TestClient

import embedding.main as embedding_module
from embedding.main import app, gemini_embed, hash_embed, validate_provider_vectors


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
    assert body["model"] == "hash-embedding-v1"
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


def test_health_reports_selected_provider(monkeypatch) -> None:
    monkeypatch.setenv("EMBEDDING_PROVIDER", "hash")
    client = TestClient(app)

    response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["provider"] == "hash"


def test_health_reports_provider_model(monkeypatch) -> None:
    monkeypatch.setenv("EMBEDDING_PROVIDER", "gemini")
    monkeypatch.setenv("GEMINI_EMBEDDING_MODEL", "gemini-embedding-001")
    client = TestClient(app)

    response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["provider"] == "gemini"
    assert response.json()["model"] == "gemini-embedding-001"


def test_embed_endpoint_rejects_unsupported_provider(monkeypatch) -> None:
    monkeypatch.setenv("EMBEDDING_PROVIDER", "unknown")
    client = TestClient(app)

    response = client.post(
        "/v1/embed",
        json={"texts": ["messy honest travel story"], "dimensions": 16},
    )

    assert response.status_code == 503


def test_gemini_provider_requires_api_key(monkeypatch) -> None:
    monkeypatch.setenv("EMBEDDING_PROVIDER", "gemini")
    monkeypatch.delenv("GEMINI_API_KEY", raising=False)
    client = TestClient(app)

    response = client.post(
        "/v1/embed",
        json={"texts": ["messy honest travel story"], "dimensions": 16},
    )

    assert response.status_code == 503
    assert "GEMINI_API_KEY" in response.json()["detail"]


def test_gemini_provider_requests_expected_dimensions(monkeypatch) -> None:
    captured_payload: dict = {}

    def fake_post_json(
        url: str,
        payload: dict,
        headers: dict[str, str],
        timeout_seconds: float,
    ) -> dict:
        captured_payload.update(payload)

        return {"embeddings": [{"values": [0.25] * 16}]}

    monkeypatch.setenv("GEMINI_API_KEY", "fake-key")
    monkeypatch.setenv("GEMINI_EMBEDDING_MODEL", "gemini-embedding-001")
    monkeypatch.setattr(embedding_module, "post_json", fake_post_json)

    vectors = gemini_embed(["messy honest travel story"], 16)
    request = captured_payload["requests"][0]

    assert len(vectors[0]) == 16
    assert request["outputDimensionality"] == 16
    assert request["taskType"] == "RETRIEVAL_DOCUMENT"


def test_provider_vectors_must_match_requested_dimensions() -> None:
    with pytest.raises(HTTPException) as exception:
        validate_provider_vectors("Test", [[0.1, 0.2, 0.3]], 1, 2)

    assert "expected 2" in str(exception.value)


def test_cohere_provider_requires_api_key(monkeypatch) -> None:
    monkeypatch.setenv("EMBEDDING_PROVIDER", "cohere")
    monkeypatch.delenv("COHERE_API_KEY", raising=False)
    client = TestClient(app)

    response = client.post(
        "/v1/embed",
        json={"texts": ["messy honest travel story"], "dimensions": 16},
    )

    assert response.status_code == 503
    assert "COHERE_API_KEY" in response.json()["detail"]
