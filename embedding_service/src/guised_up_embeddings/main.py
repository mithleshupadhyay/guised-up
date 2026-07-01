import hmac
import math
import os
import re
import zlib
from typing import Annotated

from fastapi import FastAPI, Header, HTTPException, status
from pydantic import BaseModel, Field


DEFAULT_DIMENSIONS = int(os.getenv("EMBEDDING_DIMENSIONS", "384"))
MODEL_NAME = os.getenv("EMBEDDING_MODEL_NAME", "hash-embedding-v1")
TOKEN_RE = re.compile(r"[^a-z0-9]+")

app = FastAPI(
    title="Guised Up Embedding Service",
    version="0.1.0",
    description="Deterministic embedding service used by the Real Connections Feed.",
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


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "model": MODEL_NAME}


@app.post("/v1/embed")
def embed(
    request: EmbedRequest,
    x_embedding_service_token: Annotated[
        str | None,
        Header(alias="X-Embedding-Service-Token"),
    ] = None,
) -> EmbedResponse:
    require_service_token(x_embedding_service_token)
    embeddings = [
        EmbeddingItem(index=index, vector=hash_embed(text, request.dimensions))
        for index, text in enumerate(request.texts)
    ]

    return EmbedResponse(
        model=MODEL_NAME,
        dimensions=request.dimensions,
        embeddings=embeddings,
    )
