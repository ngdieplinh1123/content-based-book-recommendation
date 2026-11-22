"""Chọn cấu hình bằng tập VALIDATION (không dùng tập test).

Chạy (từ thư mục gốc của dự án):
    python -m experiments.content_based.tune_on_validation

Thử các cấu hình TF-IDF (a-k, baseline) x các mức recency (r09, r08, r07) với
lịch sử 25% gần nhất, đánh giá trên data/processed/validation.csv, rồi (nếu có
file embedding) thử các mức recency cho Jina.
Kết quả: in bảng xếp hạng và lưu results/tuning_validation.csv
"""

import contextlib
import io
from pathlib import Path

import numpy as np
import pandas as pd

from experiments.content_based.run import (
    BATCH_SIZE,
    BOOKS_FILE,
    CANDIDATE_K,
    RECENCY_CONFIGS,
    TFIDF_CONFIGS,
    TRAIN_FILE,
)
from recommendation.evaluation.evaluator import evaluate_recommendations
from recommendation.models.content_based import (
    build_tfidf,
    build_user_profiles,
    recommend_users_in_batches,
)

VALIDATION_FILE = "data/processed/validation.csv"
EMBEDDINGS_FILE = "artifacts/jina_small_embeddings.npy"
OUTPUT_FILE = "results/tuning_validation.csv"
HISTORY_RATIO = 0.25


def quiet(function, *args, **kwargs):
    """Chạy hàm nhưng ẩn các dòng in tiến độ."""
    with contextlib.redirect_stdout(io.StringIO()):
        return function(*args, **kwargs)


def main():
    dtypes = {"user_id": str, "book_id": str}
    validation = pd.read_csv(VALIDATION_FILE, dtype=dtypes)
    train = pd.read_csv(TRAIN_FILE, dtype=dtypes)

    # Chỉ cần lịch sử của những người dùng có mặt trong validation
    users = set(validation["user_id"])
    train = train[train["user_id"].isin(users)].copy()
    seen_books = train.groupby("user_id")["book_id"].apply(set).to_dict()

    print(f"Validation: {len(validation)} người dùng")
    rows = []

    for tfidf_name, config in TFIDF_CONFIGS.items():
        books, _, tfidf_matrix = build_tfidf(BOOKS_FILE, **config)

        for recency_name, decay in RECENCY_CONFIGS.items():
            user_ids, profiles = build_user_profiles(
                train_df=train,
                books=books,
                tfidf_matrix=tfidf_matrix,
                history_ratio=HISTORY_RATIO,
                recency_decay=decay,
            )
            recommendations = quiet(
                recommend_users_in_batches,
                user_ids=user_ids,
                user_profiles=profiles,
                books=books,
                tfidf_matrix=tfidf_matrix,
                seen_books=seen_books,
                batch_size=BATCH_SIZE,
                candidate_k=CANDIDATE_K,
                top_k=10,
            )
            metrics = evaluate_recommendations(recommendations, validation, (5, 10))
            rows.append({"model": "tfidf", "tfidf_config": tfidf_name,
                         "recency": recency_name, **metrics})
            print(f"tfidf {tfidf_name:8s} {recency_name}: "
                  f"HR@10 = {metrics['Hit Rate@10']:.4f}")

    if Path(EMBEDDINGS_FILE).exists():
        from recommendation.models.semantic import generate_recommendations

        books = pd.read_csv(BOOKS_FILE, dtype={"book_id": str, "work_id": str})
        embeddings = np.load(EMBEDDINGS_FILE).astype(np.float32)

        for recency_name, decay in RECENCY_CONFIGS.items():
            frame = quiet(
                generate_recommendations,
                books=books,
                train_df=train,
                book_embeddings=embeddings,
                history_ratio=HISTORY_RATIO,
                decay=decay,
                candidate_k=CANDIDATE_K,
                top_k=10,
                batch_size=BATCH_SIZE,
            )
            recommendations = (
                frame.sort_values(["user_id", "rank"])
                .groupby("user_id")["book_id"].apply(list).to_dict()
            )
            metrics = evaluate_recommendations(recommendations, validation, (5, 10))
            rows.append({"model": "jina", "tfidf_config": "-",
                         "recency": recency_name, **metrics})
            print(f"jina {recency_name}: HR@10 = {metrics['Hit Rate@10']:.4f}")
    else:
        print(f"(bỏ qua Jina: chưa có {EMBEDDINGS_FILE})")

    table = pd.DataFrame(rows).sort_values(
        ["model", "Hit Rate@10"], ascending=[True, False]
    )
    Path(OUTPUT_FILE).parent.mkdir(parents=True, exist_ok=True)
    table.to_csv(OUTPUT_FILE, index=False)

    print()
    print(table.to_string(index=False, float_format=lambda x: f"{x:.4f}"))
    print(f"\nĐã lưu: {OUTPUT_FILE}")


if __name__ == "__main__":
    main()
