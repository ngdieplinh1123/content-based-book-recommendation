import numpy as np

# =========================
# Tính Hit Rate
# =========================
def hit_rate_at_k(recommended_books, test_book, k):
    top_k = recommended_books[:k]
    return int(test_book in top_k)

# =========================
# Tính DCG
# =========================
def dcg_at_k(recommended_books, test_book, k):
    top_k = recommended_books[:k]

    for rank, book_id in enumerate(top_k, start=1):
        if book_id == test_book:
            return 1 / np.log2(rank + 1)

    return 0.0

# =========================
# Tính NDCG
# =========================
def ndcg_at_k(recommended_books, test_book, k):
    dcg = dcg_at_k(
        recommended_books,
        test_book,
        k
    )

    # Mỗi user chỉ có 1 item relevant
    idcg = 1.0

    return dcg / idcg