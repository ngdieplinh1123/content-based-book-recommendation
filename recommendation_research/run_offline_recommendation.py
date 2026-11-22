"""Build personalized CatBook recommendations and publish them to MySQL.

Run from this directory after installing requirements-web.txt. Database
credentials can be supplied with CATBOOK_DB_HOST, CATBOOK_DB_PORT,
CATBOOK_DB_NAME, CATBOOK_DB_USER, and CATBOOK_DB_PASSWORD.
"""

from __future__ import annotations

import argparse
import json
import os
from collections import defaultdict
from typing import Any

import numpy as np
import pandas as pd
import pymysql
from pymysql.cursors import DictCursor
from sklearn.feature_extraction.text import ENGLISH_STOP_WORDS, TfidfVectorizer

from recommendation.models.content_based import recommend_for_user
from collections import Counter

TOP_K = 10
MAX_SIGNAL_WEIGHT = 5
# Stop word tiếng Việt phổ biến, bổ sung cho metadata CatBook (không dùng vocabulary Goodreads).
VIETNAMESE_STOP_WORDS = [
    "và", "của", "là", "có", "những", "các", "một", "cho", "với", "trong",
    "được", "này", "để", "không", "khi", "đã", "cũng", "như", "từ", "về",
    "ra", "đến", "tại", "nhiều", "rất", "sách", "cuốn", "tác", "giả",
]


def connect_to_database() -> pymysql.Connection:
    """Connect to the CatBook MySQL database using environment settings."""
    return pymysql.connect(
        host=os.getenv("CATBOOK_DB_HOST", "127.0.0.1"),
        port=int(os.getenv("CATBOOK_DB_PORT", "3306")),
        user=os.getenv("CATBOOK_DB_USER", "root"),
        password=os.getenv("CATBOOK_DB_PASSWORD", ""),
        database=os.getenv("CATBOOK_DB_NAME", "catbook_db"),
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=False,
    )


def _has_product_column(cursor: DictCursor, column: str) -> bool:
    cursor.execute("SHOW COLUMNS FROM products LIKE %s", (column,))
    return cursor.fetchone() is not None


def load_books(cursor: DictCursor) -> pd.DataFrame:
    """Read active, in-stock CatBook products and map them to model fields."""
    optional_columns = ("author", "publisher", "publication_year")
    selected_columns = [
        f"p.`{column}` AS `{column}`"
        for column in optional_columns
        if _has_product_column(cursor, column)
    ]
    extra_columns = ", " + ", ".join(selected_columns) if selected_columns else ""

    cursor.execute(
        "SELECT p.id AS book_id, p.name AS title, "
        "COALESCE(p.description, '') AS description, "
        "c.name AS category_name"
        f"{extra_columns} "
        "FROM products p "
        "INNER JOIN categories c ON c.id = p.category_id "
        "WHERE c.status = 1 AND p.quantity > 0 "
        "ORDER BY p.id"
    )
    rows = cursor.fetchall()
    if not rows:
        raise RuntimeError("No active, in-stock CatBook products were found.")

    books = pd.DataFrame(rows)
    books["book_id"] = books["book_id"].astype(str)
    books["work_id"] = "catbook:" + books["book_id"]
    books["authors"] = books.get("author", pd.Series("", index=books.index)).fillna("")
    books["average_rating"] = 0.0

    optional_text_columns = [
        column for column in optional_columns if column in books.columns
    ]

    def make_shelves(row: pd.Series) -> str:
        values = [str(row["category_name"]).strip()]
        values.extend(
            str(row[column]).strip()
            for column in optional_text_columns
            if pd.notna(row[column]) and str(row[column]).strip()
        )
        return " ".join(value for value in values if value)

    books["popular_shelves"] = books.apply(make_shelves, axis=1)
    return books


def _table_exists(cursor: DictCursor, table: str) -> bool:
    cursor.execute("SHOW TABLES LIKE %s", (table,))
    return cursor.fetchone() is not None


def load_user_histories(
    cursor: DictCursor,
    valid_book_ids: set[str],
) -> dict[int, list[str]]:
    """Collect orders (not cancelled), cart adds, views, and non-low reviews."""
    signals: dict[int, dict[str, int]] = defaultdict(
        lambda: defaultdict(int)
    )

    cursor.execute(
        "SELECT o.user_id, od.product_id AS book_id, "
        "CASE WHEN o.status = 'completed' THEN 3 ELSE 2 END AS signal_weight "
        "FROM orders o "
        "INNER JOIN order_details od ON od.order_id = o.id "
        "INNER JOIN users u ON u.id = o.user_id "
        "WHERE o.status <> 'cancelled' AND u.role <> 'admin'"
    )
    for row in cursor.fetchall():
        _add_signal(signals, row, valid_book_ids)

    if _table_exists(cursor, "product_cart_events"):
        cursor.execute(
            "SELECT ce.user_id, ce.product_id AS book_id, 2 AS signal_weight "
            "FROM product_cart_events ce "
            "INNER JOIN users u ON u.id = ce.user_id "
            "WHERE u.role <> 'admin'"
        )
        for row in cursor.fetchall():
            _add_signal(signals, row, valid_book_ids)

    if _table_exists(cursor, "product_views"):
        cursor.execute(
            "SELECT pv.user_id, pv.product_id AS book_id, 1 AS signal_weight "
            "FROM product_views pv "
            "INNER JOIN users u ON u.id = pv.user_id "
            "WHERE pv.user_id IS NOT NULL AND u.role <> 'admin'"
        )
        for row in cursor.fetchall():
            _add_signal(signals, row, valid_book_ids)

    if _table_exists(cursor, "product_reviews"):
        cursor.execute(
            "SELECT r.user_id, r.product_id AS book_id, "
            "CASE WHEN r.rating >= 4 THEN 3 ELSE 1 END AS signal_weight "
            "FROM product_reviews r "
            "INNER JOIN users u ON u.id = r.user_id "
            "WHERE r.rating >= 3 AND u.role <> 'admin'"
        )
        for row in cursor.fetchall():
            _add_signal(signals, row, valid_book_ids)

    histories: dict[int, list[str]] = {}
    for user_id, weights_by_book in signals.items():
        history: list[str] = []
        for book_id, weight in weights_by_book.items():
            history.extend([book_id] * min(weight, MAX_SIGNAL_WEIGHT))
        if history:
            histories[user_id] = history
    return histories


def _add_signal(
    signals: dict[int, dict[str, int]],
    row: dict[str, Any],
    valid_book_ids: set[str],
) -> None:
    book_id = str(row["book_id"])
    if book_id not in valid_book_ids:
        return

    user_id = int(row["user_id"])
    weight = int(row["signal_weight"])
    signals[user_id][book_id] = min(
        MAX_SIGNAL_WEIGHT,
        signals[user_id][book_id] + weight,
    )


def create_recommendations_table(cursor: DictCursor) -> None:
    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS user_recommendations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            score DECIMAL(10, 8) NOT NULL,
            recommendation_rank SMALLINT UNSIGNED NOT NULL,
            generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_recommendations_user_product (user_id, product_id),
            KEY user_recommendations_user_rank (user_id, recommendation_rank),
            CONSTRAINT user_recommendations_user_fk
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT user_recommendations_product_fk
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """
    )


def segment_vietnamese(text: str) -> str:
    """Join multi-syllable Vietnamese words with underscores (needs pyvi)."""
    from pyvi import ViTokenizer

    return ViTokenizer.tokenize(text)


def build_recommendation_rows(
    books: pd.DataFrame,
    histories: dict[int, list[str]],
    method: str = "tfidf",
) -> list[tuple[int, int, float, int]]:
    """Fit the research TF-IDF model on CatBook and generate Top-K results.

    method: "tfidf" (default), "semantic" (multilingual embeddings) or
    "hybrid" (reciprocal-rank fusion of both).
    """
    books = books.reset_index(drop=True)
    books["text"] = (
        books["title"].fillna("").astype(str)
        + " "
        + books["description"].fillna("").astype(str)
        + " "
        + books["popular_shelves"].fillna("").astype(str)
    )

    if os.getenv("CATBOOK_SEGMENT") == "1":
        books["tfidf_text"] = books["text"].map(segment_vietnamese)
    else:
        books["tfidf_text"] = books["text"]

    vectorizer = TfidfVectorizer(
        lowercase=True,
        stop_words=VIETNAMESE_STOP_WORDS
        + list(ENGLISH_STOP_WORDS),
        ngram_range=(1, 2),
        min_df=1,
        max_df=1.0,
        use_idf=True,
        smooth_idf=True,
        sublinear_tf=False,
        norm="l2",
    )
    tfidf_matrix = vectorizer.fit_transform(books["tfidf_text"])

    embeddings = None
    if method in ("semantic", "hybrid"):
        from catbook_semantic import embed_texts

        embeddings = embed_texts(books["text"].tolist())

    rows: list[tuple[int, int, float, int]] = []
    for user_id, history in histories.items():
        if method == "semantic":
            from catbook_semantic import recommend_semantic

            recommendations = recommend_semantic(
                history, books, embeddings, top_k=TOP_K
            )
        else:
            recommendations = recommend_for_user_weighted(
                user_book_ids=history,
                books=books,
                tfidf_matrix=tfidf_matrix,
                top_k=len(books) if method == "hybrid" else TOP_K,
            )
            if method == "hybrid":
                from catbook_semantic import fuse_rankings, recommend_semantic

                recommendations = fuse_rankings(
                    recommendations,
                    recommend_semantic(
                        history, books, embeddings, top_k=len(books)
                    ),
                    top_k=TOP_K,
                )

        rank = 0
        for recommendation in recommendations.to_dict("records"):
            score = float(recommendation["similarity"])
            if not np.isfinite(score) or score <= 0:
                continue
            rank += 1
            rows.append(
                (
                    user_id,
                    int(recommendation["book_id"]),
                    score,
                    rank,
                )
            )
            if rank == TOP_K:
                break
    return rows

def recommend_for_user_weighted(
    user_book_ids,
    books,
    tfidf_matrix,
    top_k=10,
):
    """Recommend books using a behavior-weighted TF-IDF profile."""
    from sklearn.metrics.pairwise import cosine_similarity

    books = books.copy().reset_index(drop=True)
    books["book_id"] = books["book_id"].astype(str)
    books["work_id"] = books["work_id"].astype(str)

    # Số lần xuất hiện của một book_id chính là trọng số hành vi.
    weights_by_book = Counter(
        str(book_id) for book_id in user_book_ids
    )
    seen_book_ids = set(weights_by_book)

    profile_indices = [
        i
        for i, book_id in enumerate(books["book_id"])
        if book_id in seen_book_ids
    ]

    if not profile_indices:
        return pd.DataFrame(
            columns=[
                "book_id",
                "work_id",
                "title",
                "authors",
                "average_rating",
                "similarity",
            ]
        )

    profile_weights = np.asarray(
        [
            weights_by_book[books.iloc[i]["book_id"]]
            for i in profile_indices
        ],
        dtype=np.float32,
    )

    # Tổng vector TF-IDF có trọng số.
    # Chia cho tổng trọng số không ảnh hưởng cosine similarity,
    # nên không cần thực hiện phép chia đó.
    user_profile = np.asarray(
        tfidf_matrix[profile_indices].T @ profile_weights
    ).reshape(1, -1)

    if np.linalg.norm(user_profile) == 0:
        return pd.DataFrame(
            columns=[
                "book_id",
                "work_id",
                "title",
                "authors",
                "average_rating",
                "similarity",
            ]
        )

    similarities = cosine_similarity(
        user_profile,
        tfidf_matrix,
    ).ravel()

    # Loại sách đã tương tác; không chỉ giảm điểm của chúng.
    for index in profile_indices:
        similarities[index] = -1

    ranked_indices = similarities.argsort()[::-1]

    results = []
    used_work_ids = set()

    for index in ranked_indices:
        candidate = books.iloc[index]
        work_id = candidate["work_id"]

        if candidate["book_id"] in seen_book_ids:
            continue

        if work_id in used_work_ids:
            continue

        results.append(
            {
                "book_id": candidate["book_id"],
                "work_id": work_id,
                "title": candidate["title"],
                "authors": candidate["authors"],
                "average_rating": candidate["average_rating"],
                "similarity": float(similarities[index]),
            }
        )

        used_work_ids.add(work_id)

        if len(results) >= top_k:
            break

    return pd.DataFrame(
        results,
        columns=[
            "book_id",
            "work_id",
            "title",
            "authors",
            "average_rating",
            "similarity",
        ],
    )

def run_pipeline(
    user_id: int | None = None, method: str | None = None
) -> dict[str, int]:
    method = method or os.getenv("CATBOOK_RECO_METHOD", "tfidf")
    connection = connect_to_database()
    try:
        with connection.cursor() as cursor:
            create_recommendations_table(cursor)
            books = load_books(cursor)
            histories = load_user_histories(
                cursor,
                set(books["book_id"]),
            )
            if user_id is not None:
                histories = {
                    uid: h for uid, h in histories.items() if uid == user_id
                }
            rows = build_recommendation_rows(books, histories, method)

            connection.begin()
            if user_id is None:
                cursor.execute("DELETE FROM user_recommendations")
            else:
                cursor.execute(
                    "DELETE FROM user_recommendations WHERE user_id = %s",
                    (user_id,),
                )
            if rows:
                cursor.executemany(
                    "INSERT INTO user_recommendations "
                    "(user_id, product_id, score, recommendation_rank) "
                    "VALUES (%s, %s, %s, %s)",
                    rows,
                )
            connection.commit()

        return {
            "books": len(books),
            "users_with_history": len(histories),
            "recommendations_written": len(rows),
        }
    except Exception:
        connection.rollback()
        raise
    finally:
        connection.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--user-id", type=int, default=None)
    parser.add_argument(
        "--method", choices=["tfidf", "semantic", "hybrid"], default=None
    )
    args = parser.parse_args()
    print(json.dumps(run_pipeline(args.user_id, args.method), ensure_ascii=False))
