from recommendation.data.filtering import (
    create_k_core
)

INPUT_FILE = "data/processed/interactions.csv"
OUTPUT_FILE = "data/processed/interactions_5core.csv"

MIN_INTERACTIONS = 5


# =========================
# Chạy tạo 5-core
# =========================
def main():
    df = create_k_core(
        INPUT_FILE,
        OUTPUT_FILE,
        MIN_INTERACTIONS
    )

    print("=" * 50)
    print("5-CORE DATASET")
    print("=" * 50)
    print("Minimum interactions:", MIN_INTERACTIONS)
    print("Users:", df["user_id"].nunique())
    print("Books:", df["book_id"].nunique())
    print("Interactions:", len(df))
    print("File:", OUTPUT_FILE)


if __name__ == "__main__":
    main()