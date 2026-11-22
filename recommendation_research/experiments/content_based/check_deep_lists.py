"""Kiểm tra danh sách sâu (Top-100) khớp với danh sách Top-10 đã dùng cho bảng kết quả.

Chạy (từ thư mục gốc của dự án):
    python -m experiments.content_based.check_deep_lists
"""

from pathlib import Path

import pandas as pd

FOLDERS = (Path("results/recommendations/content_based"), Path("results"))
PAIRS = {
    "tfidf_r07": ("content_based_recency_r07.csv", "content_based_recency_r07_top100.csv"),
    "jina_r07": ("jina_r07.csv", "jina_r07_top100.csv"),
}


def find(name):
    for folder in FOLDERS:
        if (folder / name).exists():
            return folder / name
    raise FileNotFoundError(f"Không tìm thấy {name}")


def lists(name):
    df = pd.read_csv(find(name), dtype={"user_id": str, "book_id": str})
    df = df.sort_values(["user_id", "rank"])
    return df.groupby("user_id")["book_id"].apply(list).to_dict()


def main():
    for name, (small, deep) in PAIRS.items():
        s, d = lists(small), lists(deep)
        same = sum(1 for u in s if d.get(u, [])[:10] == s[u])
        depth = pd.Series([len(v) for v in d.values()]).describe()[["min", "max"]].tolist()
        print(f"{name}: Top-10 trùng {same}/{len(s)} người dùng ({same / len(s):.2%}); "
              f"độ sâu min/max = {depth}")


if __name__ == "__main__":
    main()
