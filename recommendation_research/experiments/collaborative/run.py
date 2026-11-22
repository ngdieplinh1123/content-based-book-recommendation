import numpy as np
import pandas as pd
import sys
from pathlib import Path

# Thêm thư mục gốc của project vào Python path
ROOT_DIR = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT_DIR))

from recommendation.models.collaborative import (
    train_svdpp,
    recommend_users_svdpp,
)


TRAIN_FILE = "data/processed/train.csv"
BOOKS_FILE = "data/processed/books_5core.csv"

TOP_K = 10
BATCH_SIZE = 128
CANDIDATE_K = 500


SVDPP_CONFIGS = {
    "f50": {
        "n_factors": 50,
        "n_epochs": 20,
        "lr_all": 0.005,
        "reg_all": 0.02,
    },
    "e40": {
        "n_factors": 100,
        "n_epochs": 40,
        "lr_all": 0.005,
        "reg_all": 0.02,
    },
}


def main():

    config_name = (
        sys.argv[1]
        if len(sys.argv) > 1
        else "f50"
    )

    if config_name not in SVDPP_CONFIGS:
        raise ValueError(
            f"Unknown config: {config_name}. "
            f"Choose from: {sorted(SVDPP_CONFIGS)}"
        )

    config = SVDPP_CONFIGS[config_name]

    output_file = (
        "results/"
        "recommendations/"
        "collaborative/"
        f"svdpp_{config_name}.csv"
    )

    train_df = pd.read_csv(
        TRAIN_FILE,
        dtype={
            "user_id": str,
            "book_id": str
        }
    )

    books = pd.read_csv(
        BOOKS_FILE,
        dtype={
            "book_id": str,
            "work_id": str
        }
    )

    print("=" * 60)
    print("COLLABORATIVE FILTERING - SVD++")
    print("=" * 60)
    print(f"Configuration: {config_name}")
    print(f"Parameters: {config}")
    print()

    print("Training SVD++...")

    model = train_svdpp(
        train_file=TRAIN_FILE,
        **config,
        random_state=42
    )

    print("Training completed.")

    # user_id -> những book đã xuất hiện trong train
    seen_books = (
        train_df
        .groupby("user_id")["book_id"]
        .apply(set)
        .to_dict()
    )

    user_ids = (
        train_df["user_id"]
        .unique()
        .tolist()
    )

    print(f"Users: {len(user_ids)}")
    print(f"Books: {len(books)}")
    print("Generating recommendations...")

    recommendations = recommend_users_svdpp(
        model=model,
        user_ids=user_ids,
        books=books,
        seen_books=seen_books,
        batch_size=BATCH_SIZE,
        candidate_k=CANDIDATE_K,
        top_k=TOP_K
    )

    rows = []

    for user_id, book_ids in recommendations.items():

        for rank, book_id in enumerate(
            book_ids,
            start=1
        ):
            rows.append(
                {
                    "user_id": user_id,
                    "book_id": book_id,
                    "rank": rank,
                }
            )

    result_df = pd.DataFrame(rows)

    result_df.to_csv(
        output_file,
        index=False
    )

    print()
    print("Done.")
    print(
        f"Recommendations: {len(result_df)}"
    )
    print(
        f"Saved to: {output_file}"
    )


if __name__ == "__main__":
    main()