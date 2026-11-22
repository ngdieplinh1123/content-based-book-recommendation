
"""Compare recommenders on CatBook with leave-one-book-out evaluation.

Behavior weights are preserved in the history used to build user profiles.
The held-out book and all of its repeated weighted entries are removed.

Methods: popularity, TF-IDF, semantic, hybrid, random, and optional TF-IDF
with Vietnamese word segmentation.

This is a non-temporal evaluation on CatBook interactions, not the Goodreads
research evaluation. The database is only read.
"""

from __future__ import annotations

import json
import random
import sys
from collections import Counter
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.feature_extraction.text import ENGLISH_STOP_WORDS, TfidfVectorizer

from catbook_semantic import embed_texts, fuse_rankings, recommend_semantic
from recommendation.evaluation.metrics import hit_rate_at_k, ndcg_at_k
from run_offline_recommendation import (
    VIETNAMESE_STOP_WORDS,
    connect_to_database,
    load_books,
    load_user_histories,
    recommend_for_user_weighted,
    segment_vietnamese,
)

K = 10
OUTPUT = (
    Path(__file__).resolve().parent.parent
    / "docs"
    / "ket_qua_catbook.json"
)


def main() -> None:
    random.seed(42)

    connection = connect_to_database()
    try:
        with connection.cursor() as cursor:
            books = load_books(cursor).reset_index(drop=True)
            histories = load_user_histories(
                cursor,
                set(books["book_id"].astype(str)),
            )
            cursor.execute("SELECT id, category_id FROM products")
            category = {
                str(row["id"]): row["category_id"]
                for row in cursor.fetchall()
            }
    finally:
        connection.close()

    # Preserve the repeated book IDs: their counts encode behavior weights.
    weighted_histories = {
        str(user_id): [str(book_id) for book_id in history]
        for user_id, history in histories.items()
    }

    # A user-book pair counts once for eligibility and popularity.
    all_unique = {
        user_id: sorted(set(history))
        for user_id, history in weighted_histories.items()
    }

    # Popularity uses all users with available histories, not only the
    # subset eligible for leave-one-out evaluation.
    popularity = Counter(
        book_id
        for user_books in all_unique.values()
        for book_id in user_books
    )

    # Evaluate only users with at least three distinct books.
    unique = {
        user_id: user_books
        for user_id, user_books in all_unique.items()
        if len(user_books) >= 3
    }

    books["book_id"] = books["book_id"].astype(str)
    books["text"] = (
        books["title"].fillna("").astype(str) + " "
        + books["description"].fillna("").astype(str) + " "
        + books["popular_shelves"].fillna("").astype(str)
    )

    # Keep these settings aligned with the TF-IDF vectorizer in
    # run_offline_recommendation.py.
    tfidf = TfidfVectorizer(
        stop_words=VIETNAMESE_STOP_WORDS + list(ENGLISH_STOP_WORDS),
        ngram_range=(1, 2),
    ).fit_transform(books["text"])

    segmented = None
    try:
        segmented_text = books["text"].map(segment_vietnamese)
        segmented = TfidfVectorizer(
            stop_words=VIETNAMESE_STOP_WORDS + list(ENGLISH_STOP_WORDS),
            ngram_range=(1, 2),
        ).fit_transform(segmented_text)
    except ImportError:
        pass

    embeddings = embed_texts(books["text"].tolist())
    all_ids = books["book_id"].tolist()

    
    def tfidf_rank(history, n):
        out = recommend_for_user_weighted(
            history,
            books,
            tfidf,
            top_k=len(books),
        )

        if out.empty:
            return pd.DataFrame(columns=["book_id", "similarity"])

        out = out[
            np.isfinite(out["similarity"])
            & (out["similarity"] > 0)
        ]

        return out.head(n).reset_index(drop=True)


    def sem_rank(history, n):
        out = recommend_semantic(
            history,
            books,
            embeddings,
            top_k=len(books),
        )

        if out.empty:
            return pd.DataFrame(columns=["book_id", "similarity"])

        out = out[
            np.isfinite(out["similarity"])
            & (out["similarity"] > 0)
        ]

        return out.head(n).reset_index(drop=True)


    methods = {
        "popularity": None,
        "random": None,
        "tfidf": lambda history: (
            tfidf_rank(history, K)["book_id"].astype(str).tolist()
        ),
        "semantic": lambda history: (
            sem_rank(history, K)["book_id"].astype(str).tolist()
        ),
        "hybrid": lambda history: fuse_rankings(
            tfidf_rank(history, len(books)),
            sem_rank(history, len(books)),
            top_k=K,
        )["book_id"].astype(str).tolist(),
    }

    if segmented is not None:
        def tfidf_pyvi_rank(history: list[str]) -> list[str]:
            result = recommend_for_user_weighted(
                history,
                books,
                segmented,
                top_k=K,
            )
            if result.empty:
                return []
            return result["book_id"].astype(str).tolist()

        methods["tfidf_pyvi"] = tfidf_pyvi_rank

    scores = {
        name: {"hit": [], "ndcg": [], "same_category": []}
        for name in methods
    }

    trial_rows = []  # ghi lại từng lượt để tính khoảng tin cậy sau

    for user_id, user_books in unique.items():
        weighted_history = weighted_histories[user_id]
        own_books = set(user_books)

        for held_out in user_books:
            # Exclude every weighted occurrence of the target book.
            rest = [
                book_id
                for book_id in weighted_history
                if book_id != held_out
            ]
            rest_set = set(rest)

            for name, recommend in methods.items():
                if name == "popularity":
                    # Subtract the current user's own contribution from
                    # global popularity so the held-out interaction does
                    # not boost its own score.
                    ranked = sorted(
                        (book_id for book_id in all_ids
                         if book_id not in rest_set),
                        key=lambda book_id: -(
                            popularity[book_id]
                            - (1 if book_id in own_books else 0)
                        ),
                    )[:K]

                elif name == "random":
                    candidates = [
                        book_id
                        for book_id in all_ids
                        if book_id not in rest_set
                    ]
                    ranked = random.sample(
                        candidates,
                        min(K, len(candidates)),
                    )

                else:
                    ranked = recommend(rest)

                scores[name]["hit"].append(
                    hit_rate_at_k(ranked, held_out, K)
                )
                scores[name]["ndcg"].append(
                    ndcg_at_k(ranked, held_out, K)
                )
                scores[name]["same_category"].append(
                    float(
                        np.mean([
                            category[book_id] == category[held_out]
                            for book_id in ranked
                        ])
                    ) if ranked else 0.0
                )

                trial_rows.append({
                    "user_id": user_id,
                    "held_out": held_out,
                    "method": name,
                    "hit": scores[name]["hit"][-1],
                    "ndcg": scores[name]["ndcg"][-1],
                    "same_category": scores[name]["same_category"][-1],
                })

    report = {
        "users_evaluated": len(unique),
        "trials": len(scores["tfidf"]["hit"]),
        "k": K,
        "evaluation_protocol": (
            "Leave-one-book-out over distinct user-book pairs; "
            "weighted history is preserved for profile construction."
        ),
        "history_weighting": (
            "Repeated book IDs returned by load_user_histories encode "
            "behavior weights. All occurrences of the held-out book "
            "are excluded from the profile."
        ),
        "results": {
            name: {
                metric: round(float(np.mean(values)), 4)
                for metric, values in metrics.items()
            }
            for name, metrics in scores.items()
        },
        "note": (
            "CatBook contains simulated users with category-oriented "
            "preferences. These results are an initial system-level "
            "evaluation and do not replace the Goodreads experiments. "
            "The leave-one-book-out protocol is not time-based."
        ),
    }

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_text(
        json.dumps(report, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )

    trials_file = Path(__file__).resolve().parent / "results" / "catbook_trials.csv"
    trials_file.parent.mkdir(parents=True, exist_ok=True)
    pd.DataFrame(trial_rows).to_csv(trials_file, index=False)
    sys.stdout.write(
        json.dumps(report, indent=2, ensure_ascii=False) + "\n"
    )


if __name__ == "__main__":
    main()
