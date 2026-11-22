"""Tạo danh sách Hybrid (RRF) cho Goodreads từ TF-IDF R07 và Jina R07.

Chạy (từ thư mục gốc của dự án):
    python -m experiments.content_based.run_hybrid

Cần có 2 file danh sách SÂU (Top-100) đã tạo bằng:
    python -m experiments.content_based.run r07 100
    python -m experiments.content_based.run_semantic_r07 100
Kết quả: results/recommendations/content_based/hybrid_r07.csv
"""

import sys
from pathlib import Path

import pandas as pd

from recommendation.models.hybrid import fuse_rankings

DEPTH = 100   # độ sâu mỗi danh sách đầu vào (ghi vào báo cáo)
RRF_K = 60
TOP_K = 10

CB_DIR = Path("results/recommendations/content_based")
FIRST_NAME = f"content_based_recency_r07_top{DEPTH}.csv"   # TF-IDF R07
SECOND_NAME = f"jina_r07_top{DEPTH}.csv"                   # Jina R07
OUTPUT_FILE = CB_DIR / "hybrid_r07.csv"


def find(name):
    # run.py ghi vào results/, run_semantic_r07.py ghi vào results/recommendations/content_based/
    for folder in (CB_DIR, Path("results")):
        if (folder / name).exists():
            return folder / name
    raise FileNotFoundError(f"Không tìm thấy {name} trong {CB_DIR} hoặc results/")


def load(path):
    df = pd.read_csv(path, dtype={"user_id": str, "book_id": str})
    df = df.sort_values(["user_id", "rank"])
    df = df[df["rank"] <= DEPTH]
    return {u: g[["book_id"]].reset_index(drop=True) for u, g in df.groupby("user_id")}


def main():
    first, second = load(find(FIRST_NAME)), load(find(SECOND_NAME))
    empty = pd.DataFrame({"book_id": []})
    rows = []
    for user in sorted(set(first) | set(second)):
        a = first.get(user, empty).assign(similarity=1.0)    # fuse_rankings chỉ dùng thứ hạng
        b = second.get(user, empty).assign(similarity=1.0)
        fused = fuse_rankings(a, b, top_k=TOP_K, rrf_k=RRF_K)
        rows += [(user, book, r) for r, book in enumerate(fused["book_id"], start=1)]

    CB_DIR.mkdir(parents=True, exist_ok=True)
    pd.DataFrame(rows, columns=["user_id", "book_id", "rank"]).to_csv(OUTPUT_FILE, index=False)
    sys.stdout.write(f"{len(set(first) | set(second))} users -> {OUTPUT_FILE}\n")


if __name__ == "__main__":
    main()
