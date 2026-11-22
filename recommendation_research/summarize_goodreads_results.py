"""Summarize the Goodreads research results (read-only).

Re-evaluates saved recommendation files against data/processed/test.csv with
the existing evaluator and compares them with a popularity baseline. No model,
artifact, or result file is modified; output goes to docs/ket_qua_goodreads.json.
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

import pandas as pd

from recommendation.evaluation.evaluator import evaluate_recommendations

ROOT = Path(__file__).resolve().parent
RESULTS = ROOT / "results" / "recommendations"
OUTPUT = ROOT.parent / "docs" / "ket_qua_goodreads.json"

FILES = {
    "tfidf_recency_r07": RESULTS / "content_based" / "content_based_recency_r07.csv",
    "jina_semantic_r07": RESULTS / "content_based" / "jina_r07.csv",
    "svd_e40": RESULTS / "collaborative" / "svd_e40.csv",
    "svdpp_e40": RESULTS / "collaborative" / "svdpp_e40.csv",
    "hybrid_r07": RESULTS / "content_based" / "hybrid_r07.csv",
}


def load_ranked(path: Path) -> dict[str, list[str]]:
    frame = pd.read_csv(path, dtype={"user_id": str, "book_id": str})
    return (
        frame.sort_values(["user_id", "rank"])
        .groupby("user_id")["book_id"]
        .apply(list)
        .to_dict()
    )


def main() -> None:
    dtypes = {"user_id": str, "book_id": str}
    train = pd.read_csv(ROOT / "data/processed/train.csv", dtype=dtypes)
    test = pd.read_csv(ROOT / "data/processed/test.csv", dtype=dtypes)
    popular = pd.read_csv(
        RESULTS / "popularity" / "popularity_ranking.csv", dtype={"book_id": str}
    )["book_id"].tolist()

    seen = train.groupby("user_id")["book_id"].apply(set).to_dict()
    popularity = {
        user: [b for b in popular if b not in seen.get(user, set())][:10]
        for user in test["user_id"].unique()
    }

    results = {
        "popularity": evaluate_recommendations(popularity, test, (5, 10))
    }
    for name, path in FILES.items():
        if path.exists():
            results[name] = evaluate_recommendations(load_ranked(path), test, (5, 10))

    report = {
        "test_users": int(test["user_id"].nunique()),
        "results": {
            name: {metric: round(value, 4) for metric, value in values.items()}
            for name, values in results.items()
        },
    }
    OUTPUT.parent.mkdir(exist_ok=True)
    OUTPUT.write_text(json.dumps(report, indent=2), encoding="utf-8")
    sys.stdout.write(json.dumps(report, indent=2) + "\n")


if __name__ == "__main__":
    main()
