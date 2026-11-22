import gzip
import json
import csv
from datetime import datetime


# =========================
# Tiền xử lý interactions
# =========================
def preprocess_interactions(
    input_file,
    output_file
):
    with gzip.open(
        input_file,
        "rt",
        encoding="utf-8"
    ) as infile:
        with open(
            output_file,
            "w",
            newline="",
            encoding="utf-8"
        ) as outfile:
            writer = csv.writer(outfile)

            # Ghi header
            writer.writerow([
                "user_id",
                "book_id",
                "rating",
                "is_read",
                "timestamp"
            ])

            # Đọc từng interaction
            invalid_dates = 0
            for line in infile:
                data = json.loads(line)

                user_id = data["user_id"]
                book_id = data["book_id"]
                rating = int(data["rating"])
                is_read = data["is_read"]

                # Chuyển date_added thành Unix timestamp
                date_added = data["date_added"]

                try:
                    timestamp = int(
                        datetime.strptime(
                            date_added,
                            "%a %b %d %H:%M:%S %z %Y"
                        ).timestamp()
                    )
                except ValueError:
                    # Giữ giá trị 0 như cũ để dữ liệu đã xử lý không đổi,
                    # nhưng đếm lại để cảnh báo.
                    timestamp = 0
                    invalid_dates += 1

                writer.writerow([
                    user_id,
                    book_id,
                    rating,
                    is_read,
                    timestamp
                ])

    if invalid_dates:
        print(
            f"Warning: {invalid_dates} interactions had an unparseable "
            "date_added and were given timestamp=0."
        )