import sys

import pandas as pd

from recommendation.models.content_based import (
    build_tfidf,
    save_tfidf,
    build_user_profiles,
    recommend_users_in_batches,
    recommend_users_by_max_history,
)

BOOKS_FILE = "data/processed/books_5core.csv"
TRAIN_FILE = "data/processed/train.csv"
OUTPUT_DIR = "artifacts"

BATCH_SIZE = 128
CANDIDATE_K = 500
TOP_K = int(sys.argv[2]) if len(sys.argv) > 2 else 10  # độ sâu danh sách (mặc định 10)


TFIDF_CONFIGS = {
    "baseline": {
        "ngram_range": (1, 1),
        "min_df": 2,
        "max_df": 0.95,
        "sublinear_tf": False,
    },
    "a": {
        "ngram_range": (1, 2),
        "min_df": 2,
        "max_df": 0.95,
        "sublinear_tf": False,
    },
    "b": {
        "ngram_range": (1, 2),
        "min_df": 2,
        "max_df": 0.95,
        "sublinear_tf": True,
    },
    "c": {
        "ngram_range": (1, 1),
        "min_df": 2,
        "max_df": 0.95,
        "sublinear_tf": True,
    },
    "d": {
        "ngram_range": (1, 2),
        "min_df": 5,
        "max_df": 0.95,
        "sublinear_tf": True,
    },
    "e": {
        "ngram_range": (1, 2),
        "min_df": 2,
        "max_df": 0.90,
        "sublinear_tf": True,
    },
        "f": {
        "ngram_range": (1, 2),
        "min_df": 3,
        "max_df": 0.95,
        "sublinear_tf": True,
    },
    "g": {
        "ngram_range": (1, 2),
        "min_df": 10,
        "max_df": 0.95,
        "sublinear_tf": True,
    },
    "h": {
        "ngram_range": (1, 2),
        "min_df": 5,
        "max_df": 0.98,
        "sublinear_tf": True,
    },
     "i": {
        "ngram_range": (1, 2),
        "min_df": 15,
        "max_df": 0.95,
        "sublinear_tf": True,
    },
    "j": {
        "ngram_range": (1, 2),
        "min_df": 20,
        "max_df": 0.95,
        "sublinear_tf": True,
    },
    "k": {
        "ngram_range": (1, 2),
        "min_df": 30,
        "max_df": 0.95,
        "sublinear_tf": True,
    },
}

RECENCY_CONFIGS = {
    "r09": 0.9,
    "r08": 0.8,
    "r07": 0.7,
    "max_r07": 0.7,
}

def main():
    recency_name = (
        sys.argv[1]
        if len(sys.argv) > 1
        else "r08"
    )

    if recency_name not in RECENCY_CONFIGS:
        raise ValueError(
            f"Unknown recency configuration: {recency_name}. "
            f"Choose from: {sorted(RECENCY_CONFIGS)}"
        )

    recency_decay = RECENCY_CONFIGS[recency_name]

    config = TFIDF_CONFIGS["g"]

    history_ratio = 0.25

    print("=" * 60)
    print("CONTENT-BASED TF-IDF EXPERIMENT")
    print("=" * 60)
    print(f"Recency configuration: {recency_name}")
    print(f"Recency decay: {recency_decay}")
    print(f"TF-IDF parameters: {config}")
    print(f"History ratio: {history_ratio}")

    books, vectorizer, tfidf_matrix = build_tfidf(
        BOOKS_FILE,
        **config
    )

    save_tfidf(
        books,
        vectorizer,
        tfidf_matrix,
        OUTPUT_DIR
    )

    train_df = pd.read_csv(
        TRAIN_FILE,
        dtype={
            "user_id": str,
            "book_id": str
        }
    )

    seen_books = (
        train_df
        .groupby("user_id")["book_id"]
        .apply(set)
        .to_dict()
    )

    print()
    print("Books:", tfidf_matrix.shape[0])
    print("Features:", tfidf_matrix.shape[1])
    print("Users:", train_df["user_id"].nunique())

    if recency_name == "max_r07":
        recommendations = recommend_users_by_max_history(
            train_df=train_df,
            books=books,
            tfidf_matrix=tfidf_matrix,
            seen_books=seen_books,
            history_ratio=history_ratio,
            decay=recency_decay,
            batch_size=BATCH_SIZE,
            candidate_k=CANDIDATE_K,
            top_k=TOP_K,
        )

    else:
        user_ids, user_profiles = build_user_profiles(
            train_df=train_df,
            books=books,
            tfidf_matrix=tfidf_matrix,
            history_ratio=history_ratio,
            recency_decay=recency_decay,
        )

        recommendations = recommend_users_in_batches(
            user_ids=user_ids,
            user_profiles=user_profiles,
            books=books,
            tfidf_matrix=tfidf_matrix,
            seen_books=seen_books,
            batch_size=BATCH_SIZE,
            candidate_k=CANDIDATE_K,
            top_k=TOP_K,
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

    recommendation_df = pd.DataFrame(rows)

    suffix = "" if TOP_K == 10 else f"_top{TOP_K}"
    output_file = (
        f"results/"
        f"content_based_recency_{recency_name}{suffix}.csv"
    )

    recommendation_df.to_csv(
        output_file,
        index=False
    )

    user_counts = (
        recommendation_df
        .groupby("user_id")
        .size()
    )

    print()
    print("Done.")
    print(
        "Recommendation rows:",
        len(recommendation_df)
    )
    print(
        "Users with fewer than 10:",
        (user_counts < TOP_K).sum()
    )
    print(
        "Saved to:",
        output_file
    )


if __name__ == "__main__":
    main()