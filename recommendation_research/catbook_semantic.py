"""Semantic (embedding) recommendations for the CatBook catalog.

The Goodreads Jina embeddings are English-only, so CatBook books are embedded
with a multilingual sentence model. The research code is not modified; this
module only adds a scorer with the same output shape as recommend_for_user.
"""

from __future__ import annotations

import hashlib
from pathlib import Path

import numpy as np
import pandas as pd

MODEL_NAME = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"
CACHE_DIR = Path(__file__).resolve().parent / "catbook_artifacts"


def embed_texts(texts: list[str], model_name: str = MODEL_NAME) -> np.ndarray:
    """Embed texts, caching the result by content hash."""
    digest = hashlib.sha1(
        (model_name + "\n" + "\n".join(texts)).encode("utf-8")
    ).hexdigest()
    cache_file = CACHE_DIR / f"embeddings_{digest[:16]}.npy"
    if cache_file.exists():
        return np.load(cache_file)

    from fastembed import TextEmbedding

    model = TextEmbedding(model_name=model_name)
    vectors = np.asarray(list(model.embed(texts)), dtype=np.float32)
    norms = np.linalg.norm(vectors, axis=1, keepdims=True)
    vectors = vectors / np.where(norms == 0, 1, norms)

    CACHE_DIR.mkdir(exist_ok=True)
    np.save(cache_file, vectors)
    return vectors


def recommend_semantic(
    user_book_ids: list[str],
    books: pd.DataFrame,
    embeddings: np.ndarray,
    top_k: int = 10,
) -> pd.DataFrame:
    """Rank unseen books using a behavior-weighted user profile."""
    from collections import Counter

    book_ids = books["book_id"].astype(str).tolist()

    if len(book_ids) != len(embeddings):
        raise ValueError(
            "Số sách trong books không khớp số embedding."
        )

    # Số lần xuất hiện biểu diễn trọng số hành vi của sách.
    weights_by_book = Counter(
        str(book_id) for book_id in user_book_ids
    )
    seen = set(weights_by_book)

    profile_indices = [
        i
        for i, book_id in enumerate(book_ids)
        if book_id in seen
    ]

    if not profile_indices:
        return pd.DataFrame(
            columns=["book_id", "similarity"]
        )

    # Hồ sơ người dùng là trung bình embedding có trọng số.
    profile_weights = np.asarray(
        [
            weights_by_book[book_ids[i]]
            for i in profile_indices
        ],
        dtype=np.float32,
    )

    profile = np.average(
        embeddings[profile_indices],
        axis=0,
        weights=profile_weights,
    )

    norm = np.linalg.norm(profile)

    if norm == 0:
        return pd.DataFrame(
            columns=["book_id", "similarity"]
        )

    # Các embedding sách đã được chuẩn hóa trong embed_texts().
    similarities = embeddings @ (profile / norm)

    # Loại toàn bộ sách đã có tương tác khỏi danh sách gợi ý.
    similarities[profile_indices] = -1

    ranked = np.argsort(-similarities)[:top_k]

    return pd.DataFrame(
        {
            "book_id": [book_ids[i] for i in ranked],
            "similarity": [
                float(similarities[i])
                for i in ranked
            ],
        }
    )


def fuse_rankings(
    first: pd.DataFrame,
    second: pd.DataFrame,
    top_k: int = 10,
    rrf_k: int = 60,
) -> pd.DataFrame:
    """Reciprocal-rank fusion of two ranked lists (columns book_id, similarity)."""
    scores: dict[str, float] = {}
    for ranking in (first, second):
        if ranking.empty:
            continue
        for rank, book_id in enumerate(ranking["book_id"].astype(str), start=1):
            scores[book_id] = scores.get(book_id, 0.0) + 1.0 / (rrf_k + rank)
    ordered = sorted(scores.items(), key=lambda item: -item[1])[:top_k]
    best = max((score for _, score in ordered), default=1.0)
    return pd.DataFrame(
        {
            "book_id": [book_id for book_id, _ in ordered],
            "similarity": [score / best for _, score in ordered],
        }
    )
