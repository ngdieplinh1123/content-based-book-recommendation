from recommendation.data.preprocessing import (
    preprocess_interactions
)

INPUT_FILE = (
    "data/raw/goodreads_interactions_poetry.json.gz"
)

OUTPUT_FILE = (
    "data/processed/interactions.csv"
)


# =========================
# Chạy preprocessing
# =========================
def main():
    preprocess_interactions(
        INPUT_FILE,
        OUTPUT_FILE
    )

    print(
        "Đã tạo:",
        OUTPUT_FILE
    )


if __name__ == "__main__":
    main()