import unittest

import pandas as pd

from run_offline_recommendation import build_recommendation_rows


class OfflineRecommendationTests(unittest.TestCase):
    def setUp(self):
        self.books = pd.DataFrame(
            [
                {
                    "book_id": "1",
                    "work_id": "catbook:1",
                    "title": "Space Galaxy Stars",
                    "description": "Science and outer space",
                    "popular_shelves": "science fiction",
                    "authors": "",
                    "average_rating": 0.0,
                },
                {
                    "book_id": "2",
                    "work_id": "catbook:2",
                    "title": "Galaxy Planet Science",
                    "description": "Space and distant planets",
                    "popular_shelves": "science fiction",
                    "authors": "",
                    "average_rating": 0.0,
                },
                {
                    "book_id": "3",
                    "work_id": "catbook:3",
                    "title": "Bread Baking Recipes",
                    "description": "Cooking homemade bread",
                    "popular_shelves": "cooking",
                    "authors": "",
                    "average_rating": 0.0,
                },
                {
                    "book_id": "4",
                    "work_id": "catbook:4",
                    "title": "Baking Dessert Kitchen",
                    "description": "Bread and dessert recipes",
                    "popular_shelves": "cooking",
                    "authors": "",
                    "average_rating": 0.0,
                },
            ]
        )

    def test_mock_users_receive_relevant_and_distinct_recommendations(self):
        rows = build_recommendation_rows(
            self.books,
            {
                901: ["1"],
                902: ["3"],
            },
        )

        top_books = {
            user_id: product_id
            for user_id, product_id, _, rank in rows
            if rank == 1
        }

        self.assertEqual(top_books, {901: 2, 902: 4})
        self.assertTrue(all(score > 0 for _, _, score, _ in rows))
        self.assertTrue(all(rank <= 10 for _, _, _, rank in rows))

    def test_seen_items_are_not_recommended(self):
        rows = build_recommendation_rows(self.books, {901: ["1"]})

        self.assertTrue(rows)
        self.assertNotIn("1", {str(product_id) for _, product_id, _, _ in rows})

    def test_users_without_history_leave_cold_start_to_web_fallback(self):
        rows = build_recommendation_rows(self.books, {})

        self.assertEqual(rows, [])


if __name__ == "__main__":
    unittest.main()
