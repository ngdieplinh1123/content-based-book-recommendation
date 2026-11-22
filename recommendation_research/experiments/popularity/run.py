from recommendation.models.popularity import build_popularity_ranking

TRAIN_FILE = "data/processed/train.csv"
OUTPUT_FILE = "results/popularity_ranking.csv"

# =========================
# Chạy Popularity Baseline
# =========================
def main():
    popularity = build_popularity_ranking(TRAIN_FILE)

    # Lưu kết quả experiment
    popularity.to_csv(OUTPUT_FILE, index=False)

    print("=" * 50)
    print("POPULARITY BASELINE")
    print("=" * 50)

    print("Số books:", len(popularity))
    print("\nTop 10 books phổ biến nhất:")
    print(popularity.head(10).to_string(index=False))
    print("\nFile:", OUTPUT_FILE)

if __name__ == "__main__":
    main()