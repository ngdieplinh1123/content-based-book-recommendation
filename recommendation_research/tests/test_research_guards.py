import tempfile
import unittest
from pathlib import Path

import pandas as pd

from recommendation.data.split import split_dataset
from recommendation.evaluation.evaluator import evaluate_recommendations


class ResearchGuardTests(unittest.TestCase):
    def test_split_handles_short_and_missing_validation_users(self):
        rows = [("u1", str(b), 5, True, b) for b in range(5)]
        rows += [("u2", "1", 5, True, 1)]
        frame = pd.DataFrame(rows, columns=["user_id", "book_id", "rating", "is_read", "timestamp"])
        with tempfile.TemporaryDirectory() as tmp:
            source = Path(tmp) / "in.csv"
            frame.to_csv(source, index=False)
            train, validation, test = split_dataset(
                source, Path(tmp) / "a.csv", Path(tmp) / "b.csv", Path(tmp) / "c.csv"
            )
        self.assertEqual(len(train), 4)
        self.assertEqual(len(test), 1)
        self.assertTrue(validation.empty)

    def test_evaluator_rejects_empty_ground_truth(self):
        with self.assertRaises(ValueError):
            evaluate_recommendations({}, pd.DataFrame(columns=["user_id", "book_id"]))


if __name__ == "__main__":
    unittest.main()
