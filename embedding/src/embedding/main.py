import json
import hmac
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


def openai_embed(texts: list[str], dimensions: int) -> list[list[float]]:
    api_key = os.getenv("OPENAI_API_KEY", "").strip()

    if not api_key:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="OPENAI_API_KEY is required when EMBEDDING_PROVIDER=openai.",
        )

    payload = {
        "input": texts,
        "model": os.getenv("OPENAI_EMBEDDING_MODEL", MODEL_NAME),
        "dimensions": dimensions,
    }
    request = urllib.request.Request(
        OPENAI_EMBEDDING_ENDPOINT,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
        },
        method="POST",
    )

    try:
        timeout = float(os.getenv("OPENAI_EMBEDDING_TIMEOUT_SECONDS", "10"))
        with urllib.request.urlopen(request, timeout=timeout) as response:
            body = json.loads(response.read().decode("utf-8"))
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

    vectors = [item.get("embedding") for item in body.get("data", [])]

    if len(vectors) != len(texts) or not all(isinstance(vector, list) for vector in vectors):
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail="Embedding provider returned an invalid response.",
        )

    return [normalize([float(value) for value in vector]) for vector in vectors]


def embed_texts(texts: list[str], dimensions: int) -> list[list[float]]:
    provider = embedding_provider()

    if provider == "hash":
        return [hash_embed(text, dimensions) for text in texts]

    if provider == "openai":
        return openai_embed(texts, dimensions)

    raise HTTPException(
        status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
        detail=f"Unsupported embedding provider: {provider}.",
    )


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "model": MODEL_NAME, "provider": embedding_provider()}


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
        model=MODEL_NAME,
        dimensions=request.dimensions,
        embeddings=embeddings,
    )
