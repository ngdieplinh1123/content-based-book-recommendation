import pandas as pd
import numpy as np

from recommendation.models.content_based import (
    build_tfidf,
    build_user_profiles
)

BOOKS_FILE = "data/processed/books_5core.csv"
TRAIN_FILE = "data/processed/train.csv"

# =========================
# Kiểm tra user profiles
# =========================
def main():
    books, _, tfidf_matrix = build_tfidf(
        BOOKS_FILE
    )

    train_df = pd.read_csv(
        TRAIN_FILE,
        dtype={
            "user_id": str,
            "book_id": str
        }
    )

    user_ids, user_profiles = build_user_profiles(
        train_df,
        books,
        tfidf_matrix
    )

    print("=" * 50)
    print("USER PROFILE MATRIX")
    print("=" * 50)
    print("Users:", len(user_ids))
    print("Features:", user_profiles.shape[1])

if __name__ == "__main__":
    main()