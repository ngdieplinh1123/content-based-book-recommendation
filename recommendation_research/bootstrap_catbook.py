"""Khoảng tin cậy bootstrap (theo người dùng) cho kết quả đánh giá CatBook.

Chạy sau khi đã chạy evaluate_catbook.py (bản có ghi results/catbook_trials.csv):
    python bootstrap_catbook.py

Vì các lượt đánh giá của cùng một người dùng không độc lập, ta lấy mẫu lại
THEO NGƯỜI DÙNG (có hoàn lại), không lấy mẫu theo từng lượt.
Đầu ra: in hai bảng và lưu results/catbook_bootstrap.csv
"""

from pathlib import Path

import numpy as np
import pandas as pd

ROOT = Path(__file__).resolve().parent
TRIALS_FILE = ROOT / "results" / "catbook_trials.csv"
OUTPUT_FILE = ROOT / "results" / "catbook_bootstrap.csv"

N_BOOT = 5000
SEED = 42
METRICS = ["hit", "ndcg", "same_category"]
PAIRS = [  # (phương pháp A, phương pháp B): đo A - B
    ("tfidf", "hybrid"),
    ("tfidf", "semantic"),
    ("tfidf", "popularity"),
    ("hybrid", "semantic"),
    ("hybrid", "popularity"),
]


def main():
    df = pd.read_csv(TRIALS_FILE, dtype={"user_id": str, "held_out": str})
    users = sorted(df["user_id"].unique())
    methods = sorted(df["method"].unique())
    n_users = len(users)
    index = {u: i for i, u in enumerate(users)}

    # tổng và số lượt theo từng người dùng
    sums = {m: np.zeros((n_users, len(METRICS))) for m in methods}
    counts = np.zeros(n_users)
    for method, part in df.groupby("method"):
        grouped = part.groupby("user_id")[METRICS].sum()
        for user, row in grouped.iterrows():
            sums[method][index[user]] = row.to_numpy()
    first = df[df["method"] == methods[0]].groupby("user_id").size()
    for user, n in first.items():
        counts[index[user]] = n

    rng = np.random.default_rng(SEED)
    weights = rng.multinomial(n_users, np.full(n_users, 1 / n_users), size=N_BOOT)
    denominator = weights @ counts                       # (N_BOOT,)

    boot = {m: (weights @ sums[m]) / denominator[:, None] for m in methods}
    estimate = {m: sums[m].sum(axis=0) / counts.sum() for m in methods}

    rows = []
    print(f"Người dùng: {n_users} | lượt đánh giá: {int(counts.sum())} | "
          f"bootstrap: {N_BOOT} lần, seed {SEED}\n")
    print("Bảng 1. Giá trị trung bình và khoảng tin cậy 95%")
    for m in methods:
        cells = []
        for j, metric in enumerate(METRICS):
            lo, hi = np.percentile(boot[m][:, j], [2.5, 97.5])
            cells.append(f"{metric}={estimate[m][j]:.4f} [{lo:.4f}; {hi:.4f}]")
            rows.append({"kind": "mean", "a": m, "b": "", "metric": metric,
                         "estimate": estimate[m][j], "ci_low": lo, "ci_high": hi})
        print(f"  {m:11s} " + " | ".join(cells))

    print("\nBảng 2. Chênh lệch giữa hai phương pháp (A - B), khoảng tin cậy 95%")
    for a, b in PAIRS:
        if a not in boot or b not in boot:
            continue
        cells = []
        for j, metric in enumerate(METRICS):
            diff = boot[a][:, j] - boot[b][:, j]
            lo, hi = np.percentile(diff, [2.5, 97.5])
            est = estimate[a][j] - estimate[b][j]
            flag = "" if lo <= 0 <= hi else "*"
            cells.append(f"{metric}={est:+.4f} [{lo:+.4f}; {hi:+.4f}]{flag}")
            rows.append({"kind": "diff", "a": a, "b": b, "metric": metric,
                         "estimate": est, "ci_low": lo, "ci_high": hi})
        print(f"  {a} - {b}: " + " | ".join(cells))
    print("\n(*) khoảng tin cậy không chứa 0: chênh lệch có ý nghĩa ở mức 95%.")

    OUTPUT_FILE.parent.mkdir(parents=True, exist_ok=True)
    pd.DataFrame(rows).to_csv(OUTPUT_FILE, index=False)
    print(f"\nĐã lưu: {OUTPUT_FILE}")


if __name__ == "__main__":
    main()
