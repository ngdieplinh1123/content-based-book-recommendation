import sys

import numpy as np
import pandas as pd

from recommendation.models.semantic import generate_recommendations


BOOKS_FILE = "data/processed/books_5core.csv"
TRAIN_FILE = "data/processed/train.csv"
EMBEDDINGS_FILE = "artifacts/jina_small_embeddings.npy"

OUTPUT_FILE = (
    "results/recommendations/"
    "content_based/jina_validation.csv"
)

HISTORY_CONFIGS = {
    "all": 1.00,
    "50": 0.50,
    "25": 0.25,
}


def main():
    history_name = (
        sys.argv[1]
        if len(sys.argv) > 1
        else "all"
    )

    if history_name not in HISTORY_CONFIGS:
        raise ValueError(
            f"Unknown history configuration: "
            f"{history_name}. "
            f"Choose from: {sorted(HISTORY_CONFIGS)}"
        )

    history_ratio = HISTORY_CONFIGS[history_name]

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

    print("=" * 60)
    print("JINA SEMANTIC VALIDATION")
    print("=" * 60)
    print(f"History: {history_name}")
    print(f"History ratio: {history_ratio}")
    print("Decay: 1.0")
    print("Books:", len(books))
    print("Embeddings:", embeddings.shape)
    print("Train interactions:", len(train_df))

    recommendations = generate_recommendations(
        books=books,
        train_df=train_df,
        book_embeddings=embeddings,
        history_ratio=history_ratio,
        decay=1.0,
        candidate_k=500,
        top_k=10,
        batch_size=128,
    )

    recommendations.to_csv(
        OUTPUT_FILE,
        index=False,
    )

    print()
    print("Recommendation rows:", len(recommendations))
    print("Users:", recommendations["user_id"].nunique())
    print("Saved to:", OUTPUT_FILE)


if __name__ == "__main__":
    main()