import os
import numpy as np
import pandas as pd


# =========================================
# Cấu hình
# =========================================

RECOMMENDATION_DIR = (
    "results/recommendations/collaborative"
)

TOP_K_VALUES = [5, 10]

VALIDATION_FILE = "data/processed/test.csv"

CONFIGS = [
    "svdpp_e40"
]

# =========================================
# Tính HR@K và NDCG@K
# =========================================

def evaluate_one_file(
    recommendation_file,
    validation_file
):
    recommendations = pd.read_csv(
        recommendation_file,
        dtype={
            "user_id": str,
            "book_id": str
        }
    )

    validation = pd.read_csv(
        validation_file,
        dtype={
            "user_id": str,
            "book_id": str
        }
    )

    # Chỉ giữ các user xuất hiện trong cả 2 tập
    validation = validation[
        validation["user_id"].isin(
            recommendations["user_id"]
        )
    ].copy()

    # Mapping:
    # user_id -> danh sách book được recommend
    rec_map = (
        recommendations
        .sort_values(["user_id", "rank"])
        .groupby("user_id")["book_id"]
        .apply(list)
        .to_dict()
    )

    hit_5 = []
    ndcg_5 = []

    hit_10 = []
    ndcg_10 = []

    for row in validation.itertuples(index=False):

        user_id = row.user_id
        target_book = row.book_id

        ranked_books = rec_map.get(
            user_id,
            []
        )

        # =========================
        # Top 5
        # =========================

        top5 = ranked_books[:5]

        if target_book in top5:
            rank = top5.index(target_book) + 1

            hit_5.append(1)

            ndcg_5.append(
                1 / np.log2(rank + 1)
            )
        else:
            hit_5.append(0)
            ndcg_5.append(0)

        # =========================
        # Top 10
        # =========================

        top10 = ranked_books[:10]

        if target_book in top10:
            rank = top10.index(target_book) + 1

            hit_10.append(1)

            ndcg_10.append(
                1 / np.log2(rank + 1)
            )
        else:
            hit_10.append(0)
            ndcg_10.append(0)

    return {
        "HR@5": np.mean(hit_5),
        "NDCG@5": np.mean(ndcg_5),
        "HR@10": np.mean(hit_10),
        "NDCG@10": np.mean(ndcg_10),
        "Users": len(validation),
    }


# =========================================
# Main
# =========================================

def main():

    results = []

    for config_name in CONFIGS:

        recommendation_file = os.path.join(
            RECOMMENDATION_DIR,
            f"{config_name}.csv"
        )

        if not os.path.exists(
            recommendation_file
        ):
            print(
                f"Không tìm thấy: "
                f"{recommendation_file}"
            )
            continue

        print(
            f"Evaluating SVD {config_name}..."
        )

        metrics = evaluate_one_file(
            recommendation_file,
            VALIDATION_FILE
        )

        metrics["config"] = config_name

        results.append(metrics)

    # =====================================
    # Bảng kết quả
    # =====================================

    result_df = pd.DataFrame(results)

    result_df = result_df[
        [
            "config",
            "HR@5",
            "NDCG@5",
            "HR@10",
            "NDCG@10",
            "Users"
        ]
    ]

    print()
    print("=" * 80)
    print("COLLABORATIVE FILTERING - VALIDATION")
    print("=" * 80)

    print(
        result_df.to_string(
            index=False,
            float_format=lambda x: f"{x:.6f}"
        )
    )


if __name__ == "__main__":
    main()