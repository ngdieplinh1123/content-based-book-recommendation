import pandas as pd


# =========================
# Tạo k-core dataset
# =========================
def create_k_core(
    input_file,
    output_file,
    min_interactions=5
):
    df = pd.read_csv(input_file)

    # Chỉ giữ rating thực tế từ 1 đến 5
    df = df[df["rating"] > 0].copy()

    # =========================
    # Lọc lặp đến khi hội tụ
    # =========================
    while True:
        user_counts = df["user_id"].value_counts()
        book_counts = df["book_id"].value_counts()

        valid_users = user_counts[
            user_counts >= min_interactions
        ].index

        valid_books = book_counts[
            book_counts >= min_interactions
        ].index

        new_df = df[
            df["user_id"].isin(valid_users)
            & df["book_id"].isin(valid_books)
        ].copy()

        if len(new_df) == len(df):
            break

        df = new_df

    # =========================
    # Lưu dataset
    # =========================
    df.to_csv(
        output_file,
        index=False
    )

    return df