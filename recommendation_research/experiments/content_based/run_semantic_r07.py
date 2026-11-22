import sys

import numpy as np
import pandas as pd

from recommendation.models.semantic import (
    generate_recommendations,
)


BOOKS_FILE = "data/processed/books_5core.csv"
TRAIN_FILE = "data/processed/train.csv"
EMBEDDINGS_FILE = "artifacts/jina_small_embeddings.npy"

TOP_K = int(sys.argv[1]) if len(sys.argv) > 1 else 10  # độ sâu danh sách

OUTPUT_FILE = (
    "results/recommendations/"
    "content_based/"
    + ("jina_r07.csv" if TOP_K == 10 else f"jina_r07_top{TOP_K}.csv")
)


def main():
    print("=" * 60)
    print("CONTENT-BASED SEMANTIC RECOMMENDATION")
    print("=" * 60)

    books = pd.read_csv(
        BOOKS_FILE,
        dtype={
            "book_id": str,
            "work_id": str,
        },
    )

    train_df = pd.read_csv(
        TRAIN_FILE,
        dtype={
            "user_id": str,
            "book_id": str,
        },
    )

    embeddings = np.load(
        EMBEDDINGS_FILE
    ).astype(np.float32)

    print("Books:", len(books))
    print("Embeddings:", embeddings.shape)
    print("Train interactions:", len(train_df))

    recommendations = generate_recommendations(
        books=books,
        train_df=train_df,
        book_embeddings=embeddings,
        history_ratio=0.25,
        decay=0.7,
        candidate_k=500,
        top_k=TOP_K,
        batch_size=128,
    )

    recommendations.to_csv(
        OUTPUT_FILE,
        index=False,
    )

    print()
    print("Recommendation rows:", len(recommendations))
    print("Saved to:", OUTPUT_FILE)


if __name__ == "__main__":
    main()