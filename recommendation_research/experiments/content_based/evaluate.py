"""Đánh giá các danh sách content-based.

Chạy (từ thư mục gốc của dự án):
    python -m experiments.content_based.evaluate            # đánh giá trên TEST
    python -m experiments.content_based.evaluate validation # đánh giá trên VALIDATION
"""

import sys
from pathlib import Path

import pandas as pd

from recommendation.evaluation.evaluator import (
    evaluate_recommendations,
)

SPLIT_FILES = {
    "test": "data/processed/test.csv",
    "validation": "data/processed/validation.csv",
}

CB_DIR = Path("results/recommendations/content_based")

# Mỗi mô hình có thể nằm ở một trong các vị trí liệt kê (lấy vị trí đầu tiên tồn tại)
RECOMMENDATIONS_FILE = {
    "tfidf_r07": [CB_DIR / "content_based_recency_r07.csv"],
    "max_r07": [
        Path("results/content_based_recency_max_r07.csv"),
        CB_DIR / "content_based_recency_max_r07.csv",
    ],
    "jina_r07": [CB_DIR / "jina_r07.csv"],
    "hybrid_r07": [CB_DIR / "hybrid_r07.csv"],
}


def load_recommendations(file_path):
    df = pd.read_csv(
        file_path,
        dtype={
            "user_id": str,
            "book_id": str,
        }
    )

    return (
        df
        .sort_values(["user_id", "rank"])
        .groupby("user_id")["book_id"]
        .apply(list)
        .to_dict()
    )


def main():
    split = sys.argv[1] if len(sys.argv) > 1 else "test"
    if split not in SPLIT_FILES:
        raise ValueError(f"Chọn một trong: {sorted(SPLIT_FILES)}")

    truth_df = pd.read_csv(
        SPLIT_FILES[split],
        dtype={
            "user_id": str,
            "book_id": str,
        }
    )

    all_results = []

    for name, candidates in RECOMMENDATIONS_FILE.items():
        file_path = next((p for p in candidates if p.exists()), None)
        if file_path is None:
            print(f"(bỏ qua {name}: chưa có file kết quả)")
            continue

        recommendations = load_recommendations(file_path)

        metrics = evaluate_recommendations(
            recommendations=recommendations,
            ground_truth=truth_df,
            top_k_values=(5, 10),
        )

        all_results.append(
            {
                "model": name,
                **metrics,
            }
        )

    results_df = pd.DataFrame(all_results)

    print("=" * 70)
    print(f"CONTENT-BASED - {split.upper()} ({len(truth_df)} người dùng)")
    print("=" * 70)

    print(
        results_df.to_string(
            index=False,
            float_format=lambda x: f"{x:.6f}"
        )
    )


if __name__ == "__main__":
    main()
