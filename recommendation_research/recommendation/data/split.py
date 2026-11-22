import pandas as pd


# =========================
# Chia dữ liệu theo thời gian
# =========================
def split_dataset(
    input_file,
    train_file,
    validation_file,
    test_file
):
    # Đọc dữ liệu
    df = pd.read_csv(input_file)

    # Sắp xếp interaction theo thời gian của từng user
    df = df.sort_values(
        ["user_id", "timestamp"]
    ).reset_index(drop=True)

    train_data = []
    validation_data = []
    test_data = []

    # =========================
    # Chia theo từng user
    # =========================
    for _, user_df in df.groupby("user_id"):
        user_df = user_df.sort_values("timestamp")
        interactions = len(user_df)

        if interactions < 2:
            continue

        if interactions >= 10:
            # Hai interaction cuối:
            # - một validation
            # - một test
            train_data.append(user_df.iloc[:-2])
            validation_data.append(user_df.iloc[-2:-1])
            test_data.append(user_df.iloc[-1:])

        else:
            # User có 5–9 interactions:
            # interaction cuối -> test
            train_data.append(user_df.iloc[:-1])
            test_data.append(user_df.iloc[-1:])

    # Gộp dữ liệu
    train_df = pd.concat(train_data) if train_data else pd.DataFrame(columns=df.columns)
    validation_df = (
        pd.concat(validation_data) if validation_data else pd.DataFrame(columns=df.columns)
    )
    test_df = pd.concat(test_data) if test_data else pd.DataFrame(columns=df.columns)

    # Lưu
    train_df.to_csv(
        train_file,
        index=False
    )

    validation_df.to_csv(
        validation_file,
        index=False
    )

    test_df.to_csv(
        test_file,
        index=False
    )

    return train_df, validation_df, test_df