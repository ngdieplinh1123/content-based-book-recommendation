import pandas as pd


TRAIN_FILE = "data/processed/train.csv"
VALIDATION_FILE = "data/processed/validation.csv"
TEST_FILE = "data/processed/test.csv"
POPULARITY_FILE = "results/popularity_ranking.csv"


def main():
    df = pd.read_csv(TRAIN_FILE)

    # =========================
    # Basic statistics
    # =========================
    n_users = df["user_id"].nunique()
    n_books = df["book_id"].nunique()
    n_interactions = len(df)

    print("=== BASIC STATISTICS ===")
    print(f"Users: {n_users}")
    print(f"Books: {n_books}")
    print(f"Interactions: {n_interactions}")

    user_counts = df.groupby("user_id").size()
    book_counts = df.groupby("book_id").size()

    print("\n=== INTERACTIONS PER USER ===")
    print(f"Mean: {user_counts.mean():.2f}")
    print(f"Median: {user_counts.median():.2f}")
    print(f"Min: {user_counts.min()}")
    print(f"Max: {user_counts.max()}")

    print("\n=== INTERACTIONS PER BOOK ===")
    print(f"Mean: {book_counts.mean():.2f}")
    print(f"Median: {book_counts.median():.2f}")
    print(f"Min: {book_counts.min()}")
    print(f"Max: {book_counts.max()}")

    # =========================
    # Rating distribution
    # =========================
    print("\n=== RATING DISTRIBUTION ===")

    rating_counts = (
        df["rating"]
        .value_counts()
        .sort_index()
    )

    rating_percent = (
        df["rating"]
        .value_counts(normalize=True)
        .sort_index()
        * 100
    )

    for rating in rating_counts.index:
        print(
            f"Rating {rating}: "
            f"{rating_counts[rating]} "
            f"({rating_percent[rating]:.2f}%)"
        )

    # =========================
    # User interaction thresholds
    # =========================
    print("\n=== USERS BY INTERACTION THRESHOLD ===")

    for threshold in [5, 10, 20, 50, 100]:
        count = (user_counts >= threshold).sum()

        print(
            f">= {threshold}: "
            f"{count} "
            f"({count / n_users:.2%})"
        )

    # =========================
    # Book interaction thresholds
    # =========================
    print("\n=== BOOKS BY INTERACTION THRESHOLD ===")

    for threshold in [5, 10, 20, 50, 100]:
        count = (book_counts >= threshold).sum()

        print(
            f">= {threshold}: "
            f"{count} "
            f"({count / n_books:.2%})"
        )

    # =========================
    # User-item matrix
    # =========================
    total_possible = n_users * n_books
    density = n_interactions / total_possible
    sparsity = 1 - density

    print("\n=== USER-ITEM MATRIX ===")
    print(f"Possible user-book pairs: {total_possible}")
    print(f"Density: {density:.8%}")
    print(f"Sparsity: {sparsity:.8%}")

    # =========================
    # Test item popularity
    # =========================
    print("\n=== TEST ITEM POPULARITY ===")

    test_df = pd.read_csv(
        TEST_FILE,
        dtype={"book_id": str}
    )

    popularity_df = pd.read_csv(
        POPULARITY_FILE,
        dtype={"book_id": str}
    )

    popularity_df["popularity_rank"] = (
        popularity_df.index + 1
    )

    test_popularity = test_df.merge(
        popularity_df[
            [
                "book_id",
                "popularity_rank",
                "interaction_count"
            ]
        ],
        on="book_id",
        how="left",
    )

    matched = test_popularity.dropna(
        subset=["popularity_rank"]
    )

    unmatched = test_popularity[
        test_popularity["popularity_rank"].isna()
    ]

    print(
        f"Test interactions: "
        f"{len(test_popularity)}"
    )

    print(
        "Test items có trong popularity ranking: "
        f"{len(matched)}"
    )

    print(
        "Test items không có trong popularity ranking: "
        f"{len(unmatched)}"
    )

    if not matched.empty:
        print("\nPopularity rank của Test item:")
        print(
            f"Mean: "
            f"{matched['popularity_rank'].mean():.2f}"
        )
        print(
            f"Median: "
            f"{matched['popularity_rank'].median():.0f}"
        )
        print(
            f"Min: "
            f"{matched['popularity_rank'].min():.0f}"
        )
        print(
            f"Max: "
            f"{matched['popularity_rank'].max():.0f}"
        )

        total_books = len(popularity_df)

        for percent in [1, 5, 10, 20]:
            threshold = total_books * percent / 100

            count = (
                matched["popularity_rank"] <= threshold
            ).sum()

            print(
                f"Test items trong Top {percent}%: "
                f"{count} "
                f"({count / len(matched):.2%})"
            )

    # =========================
    # Validation / Test ratings
    # =========================
    print("\n=== VALIDATION / TEST RATINGS ===")

    validation_df = pd.read_csv(
        VALIDATION_FILE
    )

    test_df = pd.read_csv(
        TEST_FILE
    )

    print("\nValidation:")
    print(
        validation_df["rating"]
        .value_counts()
        .sort_index()
    )

    print("\nTest:")
    print(
        test_df["rating"]
        .value_counts()
        .sort_index()
    )

    print("\nTest rating percentage:")

    test_rating_percent = (
        test_df["rating"]
        .value_counts(normalize=True)
        .sort_index()
        * 100
    )

    for rating, percent in test_rating_percent.items():
        print(
            f"Rating {rating}: {percent:.2f}%"
        )


if __name__ == "__main__":
    main()