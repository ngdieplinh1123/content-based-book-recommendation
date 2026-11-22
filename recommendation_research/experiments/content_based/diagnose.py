import pandas as pd
import numpy as np

from recommendation.models.content_based import (
    build_tfidf,
    build_user_profiles
)
from sklearn.metrics.pairwise import linear_kernel

BOOKS_FILE = "data/processed/books_5core.csv"
TRAIN_FILE = "data/processed/train.csv"
TEST_FILE = "data/processed/test.csv"

# =========================
# Chẩn đoán Content-Based
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

    test_df = pd.read_csv(
        TEST_FILE,
        dtype={
            "user_id": str,
            "book_id": str
        }
    )

    # =========================
    # Tạo user profiles từ TRAIN
    # =========================
    user_ids, user_profiles = build_user_profiles(
        train_df,
        books,
        tfidf_matrix
    )

    user_index = {
        user_id: index
        for index, user_id in enumerate(user_ids)
    }

    book_index = pd.Series(
        books.index,
        index=books["book_id"].astype(str)
    )

    # =========================
    # Chỉ chẩn đoán 1.000 user đầu
    # =========================
    sample_test = test_df.head(1000)

    similarities = []
    ranks = []

    # =========================
    # Phân tích từng user
    # =========================
    for _, row in sample_test.iterrows():
        user_id = row["user_id"]
        test_book = str(row["book_id"])

        if user_id not in user_index:
            continue

        if test_book not in book_index:
            continue

        profile_index = user_index[user_id]
        book_position = book_index[test_book]

        user_profile = user_profiles[
            profile_index
        ]

        # Similarity với toàn bộ sách
        scores = linear_kernel(
            user_profile,
            tfidf_matrix
        ).ravel()

        # Không xét sách đã xuất hiện trong TRAIN
        seen = set(
            train_df[
                train_df["user_id"] == user_id
            ]["book_id"]
            .astype(str)
        )

        scores[
            books["book_id"].astype(str).isin(seen).to_numpy()
        ] = -1

        test_score = scores[book_position]

        # Rank của test book
        rank = 1 + np.sum(
            scores > test_score
        )

        similarities.append(test_score)
        ranks.append(rank)

    # =========================
    # Tổng hợp diagnostic
    # =========================
    ranks = np.array(ranks)
    similarities = np.array(similarities)

    print("=" * 50)
    print("CONTENT-BASED DIAGNOSTIC")
    print("=" * 50)

    print("Users analyzed:", len(ranks))

    if len(ranks) == 0:
        print("Không có user hợp lệ để phân tích.")
        return

    print(
        "Average similarity:",
        f"{similarities.mean():.6f}"
    )

    print(
        "Median similarity:",
        f"{np.median(similarities):.6f}"
    )

    print(
        "Median rank:",
        int(np.median(ranks))
    )

    print(
        "Test item in Top-5:",
        f"{np.mean(ranks <= 5):.6f}"
    )

    print(
        "Test item in Top-10:",
        f"{np.mean(ranks <= 10):.6f}"
    )

    print("\nRank distribution:")

    for limit in [10, 50, 100, 500, 1000]:
        print(
            f"Top-{limit}:",
            f"{np.mean(ranks <= limit):.6f}"
        )


if __name__ == "__main__":
    main()