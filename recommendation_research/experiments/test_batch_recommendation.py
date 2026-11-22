import pandas as pd

from recommendation.models.content_based import (
    build_tfidf,
    build_user_profiles,
    recommend_users_in_batches
)

BOOKS_FILE = "data/processed/books_5core.csv"
TRAIN_FILE = "data/processed/train.csv"
OUTPUT_FILE = "data/processed/content_based_recommendations.csv"

# =========================
# Chạy Content-Based
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

    # =========================
    # Tạo user profile
    # =========================
    user_ids, user_profiles = build_user_profiles(
        train_df,
        books,
        tfidf_matrix
    )

    # =========================
    # Tạo danh sách sách đã thấy
    # =========================
    seen_books = (
        train_df.groupby("user_id")["book_id"]
        .apply(lambda x: set(x.astype(str)))
        .to_dict()
    )

    # =========================
    # Recommendation toàn bộ user
    # =========================
    recommendations = recommend_users_in_batches(
        user_ids,
        user_profiles,
        books,
        tfidf_matrix,
        seen_books,
        batch_size=128,
        candidate_k=500,
        top_k=10
    )

    # =========================
    # Chuyển recommendation thành DataFrame
    # =========================
    rows = []

    for user_id, book_ids in recommendations.items():
        for rank, book_id in enumerate(book_ids, start=1):
            rows.append({
                "user_id": user_id,
                "book_id": book_id,
                "rank": rank
            })

    recommendation_df = pd.DataFrame(rows)

    # =========================
    # Lưu recommendation
    # =========================
    recommendation_df.to_csv(
        OUTPUT_FILE,
        index=False
    )

    print("=" * 50)
    print("CONTENT-BASED RECOMMENDATION")
    print("=" * 50)
    print("Users:", len(user_ids))
    print("Recommendation rows:", len(recommendation_df))
    print("File:", OUTPUT_FILE)


if __name__ == "__main__":
    main()