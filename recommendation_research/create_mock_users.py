"""Create mock CatBook users with distinct tastes and validate recommendations.

Each category becomes a taste persona. Users get purchases, cart events and
views only from their own category, so the offline TF-IDF pipeline should
recommend different books to users of different personas.

Usage:
    python create_mock_users.py [--per-category 2] [--seed 42]
    python create_mock_users.py --remove
Mock users have usernames starting with "mock_" and cannot log in unless a
bcrypt hash is passed with --password-hash.
"""

from __future__ import annotations

import argparse
import json
import random
from collections import defaultdict
from itertools import combinations
from typing import Any

from run_offline_recommendation import TOP_K, connect_to_database, run_pipeline

PREFIX = "mock_"
MIN_BOOKS_PER_PERSONA = 8


def remove_mock_users(cursor: Any) -> int:
    cursor.execute("SELECT id FROM users WHERE username LIKE %s", (PREFIX + "%",))
    ids = [row["id"] for row in cursor.fetchall()]
    if not ids:
        return 0
    marks = ",".join(["%s"] * len(ids))
    cursor.execute(
        f"DELETE FROM order_details WHERE order_id IN "
        f"(SELECT id FROM orders WHERE user_id IN ({marks}))",
        ids,
    )
    cursor.execute(f"DELETE FROM orders WHERE user_id IN ({marks})", ids)
    for table in ("product_views", "product_cart_events"):
        cursor.execute("SHOW TABLES LIKE %s", (table,))
        if cursor.fetchone():
            cursor.execute(f"DELETE FROM {table} WHERE user_id IN ({marks})", ids)
    cursor.execute(f"DELETE FROM users WHERE id IN ({marks})", ids)
    return len(ids)


def ensure_signal_tables(cursor: Any) -> None:
    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS product_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            session_key VARCHAR(64) NOT NULL,
            product_id INT NOT NULL,
            viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id, viewed_at),
            KEY idx_session (session_key, viewed_at),
            KEY idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """
    )
    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS product_cart_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id, created_at),
            KEY idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """
    )


def load_personas(cursor: Any) -> dict[int, dict[str, Any]]:
    cursor.execute(
        "SELECT c.id AS category_id, c.name AS category_name, p.id AS product_id, "
        "p.price, p.discount_price "
        "FROM categories c INNER JOIN products p ON p.category_id = c.id "
        "WHERE c.status = 1 AND p.quantity > 0 ORDER BY c.id, p.id"
    )
    personas: dict[int, dict[str, Any]] = {}
    for row in cursor.fetchall():
        persona = personas.setdefault(
            row["category_id"],
            {"name": row["category_name"], "books": []},
        )
        price = row["discount_price"] or row["price"]
        persona["books"].append((row["product_id"], int(price or 0)))
    return {
        cid: persona
        for cid, persona in personas.items()
        if len(persona["books"]) >= MIN_BOOKS_PER_PERSONA
    }


def create_mock_users(
    cursor: Any,
    personas: dict[int, dict[str, Any]],
    per_category: int,
    seed: int,
    password_hash: str,
) -> dict[int, int]:
    """Return {user_id: category_id} for the created mock users."""
    rng = random.Random(seed)
    created: dict[int, int] = {}
    for category_id, persona in personas.items():
        for index in range(1, per_category + 1):
            cursor.execute(
                "INSERT INTO users (username, password, role, phone, address) "
                "VALUES (%s, %s, 'user', '0900000000', 'Mock user')",
                (f"{PREFIX}cat{category_id}_{index}", password_hash),
            )
            user_id = cursor.lastrowid
            created[user_id] = category_id

            chosen = rng.sample(persona["books"], 5)
            bought, carted, viewed = chosen[:2], chosen[2:3], chosen[3:]

            cursor.execute(
                "INSERT INTO orders (user_id, total_amount, status, "
                "shipping_address, shipping_phone, payment_method) "
                "VALUES (%s, %s, 'completed', 'Mock user', '0900000000', 'cod')",
                (user_id, sum(price for _, price in bought)),
            )
            order_id = cursor.lastrowid
            cursor.executemany(
                "INSERT INTO order_details (order_id, product_id, quantity, price) "
                "VALUES (%s, %s, 1, %s)",
                [(order_id, pid, price) for pid, price in bought],
            )
            cursor.executemany(
                "INSERT INTO product_cart_events (user_id, product_id) VALUES (%s, %s)",
                [(user_id, pid) for pid, _ in carted],
            )
            cursor.executemany(
                "INSERT INTO product_views (user_id, session_key, product_id) "
                "VALUES (%s, 'mock', %s)",
                [(user_id, pid) for pid, _ in viewed],
            )
    return created


def evaluate(
    cursor: Any,
    created: dict[int, int],
    personas: dict[int, dict[str, Any]],
) -> dict[str, Any]:
    """Compare personalised lists with the popularity baseline."""
    cursor.execute("SELECT id, category_id FROM products")
    category_of = {row["id"]: row["category_id"] for row in cursor.fetchall()}

    marks = ",".join(["%s"] * len(created))
    cursor.execute(
        f"SELECT user_id, product_id FROM user_recommendations "
        f"WHERE user_id IN ({marks}) ORDER BY user_id, recommendation_rank",
        list(created),
    )
    recs: dict[int, list[int]] = defaultdict(list)
    for row in cursor.fetchall():
        recs[row["user_id"]].append(row["product_id"])

    cursor.execute(
        "SELECT od.product_id FROM order_details od "
        "INNER JOIN orders o ON o.id = od.order_id "
        "WHERE o.status <> 'cancelled' AND o.user_id NOT IN (%s) "
        "GROUP BY od.product_id ORDER BY SUM(od.quantity) DESC, od.product_id "
        "LIMIT %d" % (marks, TOP_K),
        list(created),
    )
    popular = [row["product_id"] for row in cursor.fetchall()]

    cursor.execute(
        f"SELECT user_id, product_id FROM product_views WHERE user_id IN ({marks}) "
        f"UNION SELECT user_id, product_id FROM product_cart_events "
        f"WHERE user_id IN ({marks}) "
        f"UNION SELECT o.user_id, od.product_id FROM orders o "
        f"INNER JOIN order_details od ON od.order_id = o.id "
        f"WHERE o.user_id IN ({marks})",
        list(created) * 3,
    )
    seen: dict[int, set[int]] = defaultdict(set)
    for row in cursor.fetchall():
        seen[row["user_id"]].add(row["product_id"])

    users: list[dict[str, Any]] = []
    for user_id, category_id in created.items():
        items = recs.get(user_id, [])
        in_taste = sum(1 for pid in items if category_of.get(pid) == category_id)
        popular_in_taste = sum(
            1 for pid in popular if category_of.get(pid) == category_id
        )
        users.append(
            {
                "user_id": user_id,
                "taste": personas[category_id]["name"],
                "recommended": len(items),
                "taste_precision": round(in_taste / max(len(items), 1), 2),
                "popular_taste_precision": round(popular_in_taste / max(len(popular), 1), 2),
                "repeats_seen_item": any(pid in seen[user_id] for pid in items),
            }
        )

    different_taste_overlap = [
        len(set(recs[a]) & set(recs[b])) / TOP_K
        for a, b in combinations(created, 2)
        if created[a] != created[b] and recs.get(a) and recs.get(b)
    ]
    count = max(len(users), 1)
    return {
        "mock_users": len(users),
        "avg_taste_precision": round(sum(u["taste_precision"] for u in users) / count, 3),
        "avg_popular_taste_precision": round(
            sum(u["popular_taste_precision"] for u in users) / count, 3
        ),
        "avg_overlap_between_different_tastes": round(
            sum(different_taste_overlap) / max(len(different_taste_overlap), 1), 3
        ),
        "users_with_seen_items_in_top_k": sum(u["repeats_seen_item"] for u in users),
        "users": users,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--per-category", type=int, default=2)
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--password-hash", default="!")
    parser.add_argument("--remove", action="store_true")
    args = parser.parse_args()

    connection = connect_to_database()
    try:
        with connection.cursor() as cursor:
            removed = remove_mock_users(cursor)
            if args.remove:
                connection.commit()
                print(json.dumps({"removed": removed}))
                return
            ensure_signal_tables(cursor)
            personas = load_personas(cursor)
            created = create_mock_users(
                cursor, personas, args.per_category, args.seed, args.password_hash
            )
        connection.commit()

        run_pipeline()

        with connection.cursor() as cursor:
            report = evaluate(cursor, created, personas)
        report["personas"] = [p["name"] for p in personas.values()]
        with open("mock_users_report.json", "w", encoding="utf-8") as handle:
            json.dump(report, handle, ensure_ascii=False, indent=2)
        summary = {k: v for k, v in report.items() if k not in ("users", "personas")}
        print(json.dumps(summary, indent=2))
        print("Chi tiet tung user: mock_users_report.json")
    except Exception:
        connection.rollback()
        raise
    finally:
        connection.close()


if __name__ == "__main__":
    main()
