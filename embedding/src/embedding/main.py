import hmac
import json
import math
import os
import re
import urllib.error
import urllib.request
import zlib
from typing import Annotated

from fastapi import FastAPI, Header, HTTPException, status
from pydantic import BaseModel, Field


DEFAULT_DIMENSIONS = int(os.getenv("EMBEDDING_DIMENSIONS", "384"))
MODEL_NAME = os.getenv("EMBEDDING_MODEL_NAME", "hash-embedding-v1")
OPENAI_EMBEDDING_ENDPOINT = os.getenv(
    "OPENAI_EMBEDDING_ENDPOINT",
    "https://api.openai.com/v1/embeddings",
)
GEMINI_EMBEDDING_ENDPOINT = os.getenv(
    "GEMINI_EMBEDDING_ENDPOINT",
    "https://generativelanguage.googleapis.com/v1beta/models/{model}:batchEmbedContents",
)
COHERE_EMBEDDING_ENDPOINT = os.getenv(
    "COHERE_EMBEDDING_ENDPOINT",
    "https://api.cohere.com/v2/embed",
)
TOKEN_RE = re.compile(r"[^a-z0-9]+")

app = FastAPI(
    title="Guised Up Embedding Service",
    version="0.1.0",
    description="Embedding service used by the Real Connections Feed.",
)


class EmbedRequest(BaseModel):
    texts: Annotated[list[str], Field(min_length=1, max_length=64)]
    dimensions: Annotated[int, Field(ge=8, le=4096)] = DEFAULT_DIMENSIONS


class EmbeddingItem(BaseModel):
    index: int
    vector: list[float]


class EmbedResponse(BaseModel):
    model: str
    dimensions: int
    embeddings: list[EmbeddingItem]


def require_service_token(provided_token: str | None) -> None:
    expected_token = os.getenv("EMBEDDING_SERVICE_TOKEN", "")

    if expected_token and not hmac.compare_digest(
        provided_token or "",
        expected_token,
    ):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid embedding service token.",
        )


def normalize(vector: list[float]) -> list[float]:
    norm = math.sqrt(sum(value * value for value in vector))

    if norm <= 0:
        return vector

    return [value / norm for value in vector]


def embedding_provider() -> str:
    return os.getenv("EMBEDDING_PROVIDER", "hash").strip().lower()


def selected_model_name() -> str:
    provider = embedding_provider()

    if provider == "openai":
        return os.getenv("OPENAI_EMBEDDING_MODEL", "text-embedding-3-small")

    if provider == "gemini":
        return os.getenv("GEMINI_EMBEDDING_MODEL", "gemini-embedding-001")

    if provider == "cohere":
        return os.getenv("COHERE_EMBEDDING_MODEL", "embed-english-light-v3.0")

    return MODEL_NAME


def hash_embed(text: str, dimensions: int) -> list[float]:
    tokens = [token for token in TOKEN_RE.split(text.lower()) if token] or ["empty"]
    vector = [0.0] * dimensions

    for position, token in enumerate(tokens):
        hashed = zlib.crc32(token.encode("utf-8"))
        index = hashed % dimensions
        sign = 1.0 if hashed & 1 else -1.0
        length_boost = 1.0 + min(len(token), 16) / 16
        position_decay = 1.0 / math.sqrt(position + 1)
        vector[index] += sign * length_boost * position_decay

    return normalize(vector)


def post_json(
    url: str,
    payload: dict,
    headers: dict[str, str],
    timeout_seconds: float,
) -> dict:
    request = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json", **headers},
        method="POST",
    )

    try:
        with urllib.request.urlopen(request, timeout=timeout_seconds) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exception:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=f"Embedding provider returned HTTP {exception.code}.",
        ) from exception
    except (OSError, json.JSONDecodeError) as exception:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail="Embedding provider request failed.",
        ) from exception


def validate_provider_vectors(
    provider_name: str,
    vectors: list | None,
    expected_count: int,
    expected_dimensions: int,
) -> list[list[float]]:
    if (
        vectors is None
        or len(vectors) != expected_count
        or not all(isinstance(vector, list) for vector in vectors)
    ):
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=f"{provider_name} embedding provider returned an invalid response.",
        )

    normalized_vectors: list[list[float]] = []

    try:
        for vector in vectors:
            if len(vector) != expected_dimensions:
                raise HTTPException(
                    status_code=status.HTTP_502_BAD_GATEWAY,
                    detail=(
                        f"{provider_name} embedding provider returned "
                        f"{len(vector)} dimensions; expected {expected_dimensions}."
                    ),
                )

            normalized_vectors.append(normalize([float(value) for value in vector]))

        return normalized_vectors
    except (TypeError, ValueError) as exception:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=f"{provider_name} embedding provider returned non-numeric values.",
        ) from exception


def openai_embed(texts: list[str], dimensions: int) -> list[list[float]]:
    api_key = os.getenv("OPENAI_API_KEY", "").strip()

    if not api_key:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="OPENAI_API_KEY is required when EMBEDDING_PROVIDER=openai.",
        )

    payload = {
        "input": texts,
        "model": selected_model_name(),
        "dimensions": dimensions,
    }
    body = post_json(
        OPENAI_EMBEDDING_ENDPOINT,
        payload,
        headers={
            "Authorization": f"Bearer {api_key}",
        },
        timeout_seconds=float(os.getenv("OPENAI_EMBEDDING_TIMEOUT_SECONDS", "10")),
    )

    vectors = [item.get("embedding") for item in body.get("data", [])]

    return validate_provider_vectors("OpenAI", vectors, len(texts), dimensions)


def gemini_embed(texts: list[str], dimensions: int) -> list[list[float]]:
    api_key = os.getenv("GEMINI_API_KEY", "").strip()

    if not api_key:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="GEMINI_API_KEY is required when EMBEDDING_PROVIDER=gemini.",
        )

    model = selected_model_name()
    model_resource = model if model.startswith("models/") else f"models/{model}"
    payload = {
        "requests": [
            {
                "model": model_resource,
                "content": {"parts": [{"text": text}]},
                "outputDimensionality": dimensions,
                "taskType": os.getenv("GEMINI_EMBEDDING_TASK_TYPE", "RETRIEVAL_DOCUMENT"),
            }
            for text in texts
        ],
    }
    body = post_json(
        GEMINI_EMBEDDING_ENDPOINT.format(model=model_resource.removeprefix("models/")),
        payload,
        headers={"x-goog-api-key": api_key},
        timeout_seconds=float(os.getenv("GEMINI_EMBEDDING_TIMEOUT_SECONDS", "10")),
    )
    vectors = [item.get("values") for item in body.get("embeddings", [])]

    return validate_provider_vectors("Gemini", vectors, len(texts), dimensions)


def cohere_embed(texts: list[str], dimensions: int) -> list[list[float]]:
    api_key = os.getenv("COHERE_API_KEY", "").strip()

    if not api_key:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="COHERE_API_KEY is required when EMBEDDING_PROVIDER=cohere.",
        )

    payload = {
        "texts": texts,
        "model": selected_model_name(),
        "input_type": os.getenv("COHERE_EMBEDDING_INPUT_TYPE", "search_document"),
        "embedding_types": ["float"],
        "truncate": os.getenv("COHERE_EMBEDDING_TRUNCATE", "END"),
    }
    body = post_json(
        COHERE_EMBEDDING_ENDPOINT,
        payload,
        headers={"Authorization": f"Bearer {api_key}"},
        timeout_seconds=float(os.getenv("COHERE_EMBEDDING_TIMEOUT_SECONDS", "10")),
    )
    vectors = body.get("embeddings", {}).get("float")

    return validate_provider_vectors("Cohere", vectors, len(texts), dimensions)


def embed_texts(texts: list[str], dimensions: int) -> list[list[float]]:
    provider = embedding_provider()

    if provider == "hash":
        return [hash_embed(text, dimensions) for text in texts]

    if provider == "openai":
        return openai_embed(texts, dimensions)

    if provider == "gemini":
        return gemini_embed(texts, dimensions)

    if provider == "cohere":
        return cohere_embed(texts, dimensions)

    raise HTTPException(
        status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
        detail=f"Unsupported embedding provider: {provider}.",
    )


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "model": selected_model_name(), "provider": embedding_provider()}


@app.post("/v1/embed")
def embed(
    request: EmbedRequest,
    x_embedding_service_token: Annotated[
        str | None,
        Header(alias="X-Embedding-Service-Token"),
    ] = None,
) -> EmbedResponse:
    require_service_token(x_embedding_service_token)
    vectors = embed_texts(request.texts, request.dimensions)
    embeddings = [
        EmbeddingItem(index=index, vector=vector)
        for index, vector in enumerate(vectors)
    ]

    return EmbedResponse(
        model=selected_model_name(),
        dimensions=request.dimensions,
        embeddings=embeddings,
    )
