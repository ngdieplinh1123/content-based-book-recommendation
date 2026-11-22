import pandas as pd

# =========================
# Tạo bảng xếp hạng độ phổ biến
# =========================
def build_popularity_ranking(train_file):
    df = pd.read_csv(train_file)

    popularity = (
        df.groupby("book_id")
        .size()
        .reset_index(name="interaction_count")
        .sort_values("interaction_count", ascending=False)
        .reset_index(drop=True)
    )

    return popularity