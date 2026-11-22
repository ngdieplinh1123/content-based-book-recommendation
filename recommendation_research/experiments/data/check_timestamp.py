import pandas as pd

INPUT_FILE = "data/processed/interactions.csv"

df = pd.read_csv(INPUT_FILE)

print("=== TIMESTAMP AUDIT ===")

print("Tổng số interactions:", len(df))

zero_count = (df["timestamp"] == 0).sum()
zero_ratio = zero_count / len(df)

print("timestamp = 0:", zero_count)
print("Tỷ lệ timestamp = 0:", f"{zero_ratio:.4%}")

valid = df[df["timestamp"] > 0]

if len(valid) > 0:
    print("Timestamp nhỏ nhất:", valid["timestamp"].min())
    print("Timestamp lớn nhất:", valid["timestamp"].max())

    print("\n5 timestamp nhỏ nhất:")
    print(valid["timestamp"].sort_values().head().to_list())

    print("\n5 timestamp lớn nhất:")
    print(valid["timestamp"].sort_values().tail().to_list())

print("\n=== PHÂN BỐ THEO NĂM ===")

if len(valid) > 0:
    dates = pd.to_datetime(valid["timestamp"], unit="s", errors="coerce")
    print(dates.dt.year.value_counts().sort_index())