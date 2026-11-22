import pandas as pd

from recommendation.evaluation.evaluator import (
    evaluate_recommendations
)

TRAIN_FILE = "data/processed/train.csv"
TEST_FILE = "data/processed/test.csv"
POPULARITY_FILE = "results/popularity_ranking.csv"

# =========================
# Đọc dữ liệu
# =========================
def load_data():
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

    popularity_df = pd.read_csv(
        POPULARITY_FILE,
        dtype={
            "book_id": str
        }
    )

    return train_df, test_df, popularity_df

# =========================
# Tạo recommendation cho từng user
# =========================
def build_popularity_recommendations(
    train_df,
    test_df,
    popularity_df,
    top_k=10
):
    popularity_books = (
        popularity_df["book_id"].tolist()
    )

    seen_books = (
        train_df.groupby("user_id")["book_id"]
        .apply(lambda x: set(x.astype(str)))
        .to_dict()
    )

    recommendations = {}

    for user_id in test_df["user_id"].unique():
        seen = seen_books.get(
            user_id,
            set()
        )

        ranked_books = [
            book_id
            for book_id in popularity_books
            if book_id not in seen
        ]

        recommendations[user_id] = ranked_books[:top_k]

    return recommendations

# =========================
# Chạy evaluation
# =========================
def main():
    train_df, test_df, popularity_df = load_data()

    recommendations = build_popularity_recommendations(
        train_df,
        test_df,
        popularity_df
    )

    results = evaluate_recommendations(
        recommendations,
        test_df,
        top_k_values=(5, 10)
    )

    print("=" * 50)
    print("POPULARITY BASELINE - EVALUATION")
    print("=" * 50)

    print(
        f"Hit Rate@5: {results['Hit Rate@5']:.6f}"
    )
    print(
        f"Hit Rate@10: {results['Hit Rate@10']:.6f}"
    )
    print(
        f"NDCG@5: {results['NDCG@5']:.6f}"
    )
    print(
        f"NDCG@10: {results['NDCG@10']:.6f}"
    )

if __name__ == "__main__":
    main()