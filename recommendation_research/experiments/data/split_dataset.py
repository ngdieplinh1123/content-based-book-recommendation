from recommendation.data.split import split_dataset


INPUT_FILE = "data/processed/interactions_5core.csv"

TRAIN_FILE = "data/processed/train.csv"
VALIDATION_FILE = "data/processed/validation.csv"
TEST_FILE = "data/processed/test.csv"


# =========================
# Chạy temporal split
# =========================
def main():
    train_df, validation_df, test_df = split_dataset(
        INPUT_FILE,
        TRAIN_FILE,
        VALIDATION_FILE,
        TEST_FILE
    )

    print("=" * 50)
    print("DATASET SPLIT")
    print("=" * 50)

    print("\nTrain:")
    print("Interactions:", len(train_df))
    print("Users:", train_df["user_id"].nunique())
    print("Books:", train_df["book_id"].nunique())

    print("\nValidation:")
    print("Interactions:", len(validation_df))
    print("Users:", validation_df["user_id"].nunique())
    print("Books:", validation_df["book_id"].nunique())

    print("\nTest:")
    print("Interactions:", len(test_df))
    print("Users:", test_df["user_id"].nunique())
    print("Books:", test_df["book_id"].nunique())


if __name__ == "__main__":
    main()