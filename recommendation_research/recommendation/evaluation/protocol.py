# =========================
# Dataset split
# =========================
TRAIN_SET = "train"
VALIDATION_SET = "validation"
TEST_SET = "test"

# =========================
# Ranking metrics
# =========================
METRICS = [
    "Hit Rate@5",
    "Hit Rate@10",
    "NDCG@5",
    "NDCG@10"
]

# =========================
# Recommendation settings
# =========================
TOP_K_VALUES = [5, 10]

# =========================
# Evaluation rules
# =========================
EXCLUDE_SEEN_ITEMS = True
ONE_TEST_ITEM_PER_USER = True