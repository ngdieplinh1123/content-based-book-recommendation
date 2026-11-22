<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config.php';

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS product_reviews (
        id INT NOT NULL AUTO_INCREMENT,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        order_id INT NOT NULL,
        rating TINYINT UNSIGNED NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY product_reviews_product_user_unique (product_id, user_id),
        KEY product_reviews_product_created_idx (product_id, created_at),
        KEY product_reviews_order_idx (order_id),
        CONSTRAINT product_reviews_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
        CONSTRAINT product_reviews_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT product_reviews_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
        CONSTRAINT product_reviews_rating_check CHECK (rating BETWEEN 1 AND 5)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "Product review table is ready.\n";
