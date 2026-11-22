import pandas as pd

FILE = "data/processed/content_based_recommendations.csv"

# =========================
# Kiểm tra số recommendation mỗi user
# =========================
def main():
    df = pd.read_csv(
        FILE,
        dtype={
            "user_id": str,
            "book_id": str
        }
    )

    counts = (
        df.groupby("user_id")
        .size()
    )

    print("=" * 50)
    print("CONTENT-BASED RECOMMENDATION CHECK")
    print("=" * 50)

    print("Users:", counts.size)
    print("Recommendation rows:", len(df))
    print("Average recommendations/user:", f"{counts.mean():.2f}")
    print("Minimum recommendations/user:", counts.min())
    print("Maximum recommendations/user:", counts.max())

    print("\nUsers with fewer than 10 recommendations:")
    print((counts < 10).sum())

    print("\nDistribution:")
    print(counts.value_counts().sort_index())

if __name__ == "__main__":
    main()