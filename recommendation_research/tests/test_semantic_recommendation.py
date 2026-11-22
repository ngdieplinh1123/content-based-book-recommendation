import unittest

import numpy as np
import pandas as pd

from catbook_semantic import fuse_rankings, recommend_semantic


class SemanticRecommendationTests(unittest.TestCase):
    def setUp(self):
        self.books = pd.DataFrame({"book_id": ["1", "2", "3", "4"]})
        self.embeddings = np.array(
            [[1, 0], [0.9, 0.1], [0, 1], [0.1, 0.9]], dtype=np.float32
        )
        norms = np.linalg.norm(self.embeddings, axis=1, keepdims=True)
        self.embeddings = self.embeddings / norms

    def test_ranks_similar_unseen_book_first(self):
        result = recommend_semantic(["1"], self.books, self.embeddings, top_k=3)
        self.assertEqual(result["book_id"].iloc[0], "2")
        self.assertNotIn("1", result["book_id"].tolist())

    def test_unknown_history_returns_empty(self):
        result = recommend_semantic(["99"], self.books, self.embeddings)
        self.assertTrue(result.empty)

    def test_fusion_prefers_books_ranked_by_both(self):
        first = pd.DataFrame({"book_id": ["a", "b"], "similarity": [1, 0.5]})
        second = pd.DataFrame({"book_id": ["b", "c"], "similarity": [1, 0.5]})
        fused = fuse_rankings(first, second, top_k=3)
        self.assertEqual(fused["book_id"].iloc[0], "b")


if __name__ == "__main__":
    unittest.main()
