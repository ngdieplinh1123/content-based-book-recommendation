"""Kiểm tra danh sách sâu (Top-100) khớp với danh sách Top-10 đã dùng cho Bảng 4.1."""
from pathlib import Path

import pandas as pd

CB = Path("results/recommendations/content_based")
PAIRS = {
    "tfidf_r07": ("content_based_recency_r07.csv", "content_based_recency_r07_top100.csv"),
    "jina_r07": ("jina_r07.csv", "jina_r07_top100.csv"),
}


def lists(path):
    df = pd.read_csv(CB / path, dtype={"user_id": str, "book_id": str}).sort_values(["user_id", "rank"])
    return df.groupby("user_id")["book_id"].apply(list).to_dict()


for name, (small, deep) in PAIRS.items():
    s, d = lists(small), lists(deep)
    same = sum(1 for u in s if d.get(u, [])[:10] == s[u])
    depth = pd.Series([len(v) for v in d.values()]).describe()[["min", "max"]].tolist()
    print(f"{name}: top-10 trùng {same}/{len(s)} người dùng ({same/len(s):.2%}); độ sâu min/max = {depth}")