"""Hybrid: hợp nhất hai danh sách xếp hạng bằng Reciprocal Rank Fusion (RRF).

Đây là cùng một thuật toán với hàm fuse_rankings trong catbook_semantic.py của CatBook.
Điểm của một sách = tổng 1 / (rrf_k + thứ_hạng) trên từng danh sách chứa sách đó.
"""

import pandas as pd


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
