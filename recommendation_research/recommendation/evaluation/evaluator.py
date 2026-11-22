import pandas as pd

from recommendation.evaluation.metrics import (
    hit_rate_at_k,
    ndcg_at_k
)

# =========================
# Đánh giá danh sách recommendation
# =========================
def evaluate_recommendations(
    recommendations,
    ground_truth,
    top_k_values=(5, 10)
):
    hit_scores = {
        k: []
        for k in top_k_values
    }

    ndcg_scores = {
        k: []
        for k in top_k_values
    }

    # =========================
    # Đánh giá từng user
    # =========================
    for _, row in ground_truth.iterrows():
        user_id = str(row["user_id"])
        test_book = str(row["book_id"])

        ranked_books = recommendations.get(
            user_id,
            []
        )

        for k in top_k_values:
            top_k = ranked_books[:k]

            hit_scores[k].append(
                hit_rate_at_k(
                    top_k,
                    test_book,
                    k
                )
            )

            ndcg_scores[k].append(
                ndcg_at_k(
                    top_k,
                    test_book,
                    k
                )
            )

    # =========================
    # Tổng hợp kết quả
    # =========================
    results = {}

    if ground_truth is None or len(ground_truth) == 0:
        raise ValueError("ground_truth is empty; nothing to evaluate.")

    for k in top_k_values:
        results[f"Hit Rate@{k}"] = (
            sum(hit_scores[k])
            / len(hit_scores[k])
        )

        results[f"NDCG@{k}"] = (
            sum(ndcg_scores[k])
            / len(ndcg_scores[k])
        )

    return results