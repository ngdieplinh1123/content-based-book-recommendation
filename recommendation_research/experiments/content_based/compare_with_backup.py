"""So sánh kết quả mới với bản sao lưu (thư mục results_backup) để xác nhận tái lập.

Chạy (từ thư mục gốc của dự án):
    python -m experiments.content_based.compare_with_backup

Với mỗi mô hình, in số người dùng có danh sách Top-10 giống hệt bản cũ và thời điểm
file mới được ghi (để chắc chắn file đã thật sự được tạo lại).
"""

from datetime import datetime
from pathlib import Path

import pandas as pd

OLD_ROOT = Path("results_backup")
NEW_ROOT = Path("results")

# (vị trí tìm file cũ, vị trí tìm file mới) - tính từ thư mục results
# File mới: ưu tiên vị trí mà run.py vừa ghi ra (results/), rồi mới tới thư mục đã chuyển vào
CB = "recommendations/content_based"
FILES = {
    "tfidf_r07": (
        [f"{CB}/content_based_recency_r07.csv", "content_based_recency_r07.csv"],
        ["content_based_recency_r07.csv", f"{CB}/content_based_recency_r07.csv"],
    ),
    "max_r07": (
        ["content_based_recency_max_r07.csv", f"{CB}/content_based_recency_max_r07.csv"],
        ["content_based_recency_max_r07.csv", f"{CB}/content_based_recency_max_r07.csv"],
    ),
    "jina_r07": ([f"{CB}/jina_r07.csv"], [f"{CB}/jina_r07.csv"]),
    "hybrid_r07": ([f"{CB}/hybrid_r07.csv"], [f"{CB}/hybrid_r07.csv"]),
}


def find(root, names):
    for name in names:
        path = root / name
        if path.exists():
            return path
    return None


def lists(path):
    df = pd.read_csv(path, dtype={"user_id": str, "book_id": str})
    df = df.sort_values(["user_id", "rank"])
    return df.groupby("user_id")["book_id"].apply(list).to_dict()


def main():
    for name, (old_names, new_names) in FILES.items():
        old = find(OLD_ROOT, old_names)
        new = find(NEW_ROOT, new_names)
        if old is None or new is None:
            print(f"{name}: thiếu file ({'cũ' if old is None else 'mới'}), bỏ qua")
            continue

        a, b = lists(old), lists(new)
        same = sum(1 for user in a if b.get(user) == a[user])
        verdict = "GIỐNG HỆT" if same == len(a) == len(b) else "CÓ KHÁC BIỆT"
        written = datetime.fromtimestamp(new.stat().st_mtime).strftime("%Y-%m-%d %H:%M")
        print(f"{name}: {verdict} - {same}/{len(a)} người dùng trùng "
              f"({same / len(a):.2%}); người dùng cũ/mới = {len(a)}/{len(b)}; "
              f"file mới ghi lúc {written}")


if __name__ == "__main__":
    main()
