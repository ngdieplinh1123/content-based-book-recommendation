import pandas as pd
import numpy as np
from scipy.sparse import load_npz
from scipy.sparse import save_npz
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.metrics.pairwise import linear_kernel
from sklearn.preprocessing import normalize
import joblib

# =========================
# Tạo TF-IDF từ metadata sách
# =========================
def build_tfidf(
    books_file,
    ngram_range=(1, 1),
    min_df=2,
    max_df=0.95,
    sublinear_tf=False
):
    books = pd.read_csv(
        books_file,
        dtype={
            "book_id": str,
            "work_id": str
        }
    )

    text_columns = [
        "title",
        "description",
        "popular_shelves"
    ]

    for column in text_columns:
        books[column] = books[column].fillna("").astype(str)

    books["text"] = (
        books["title"] + " "
        + books["description"] + " "
        + books["popular_shelves"]
    )

    vectorizer = TfidfVectorizer(
        lowercase=True,
        stop_words="english",
        ngram_range=ngram_range,
        min_df=min_df,
        max_df=max_df,
        sublinear_tf=sublinear_tf
    )

    tfidf_matrix = vectorizer.fit_transform(books["text"])

    return books, vectorizer, tfidf_matrix

# =========================
# Lưu TF-IDF artifacts
# =========================
def save_tfidf(books, vectorizer, tfidf_matrix, output_dir):
    save_npz(
        f"{output_dir}/book_tfidf.npz",
        tfidf_matrix
    )

    joblib.dump(
        vectorizer,
        f"{output_dir}/tfidf_vectorizer.joblib"
    )

    books[["book_id"]].to_csv(
        f"{output_dir}/book_ids.csv",
        index=False
    )

# =========================
# Tìm các sách tương tự
# =========================
def find_similar_books(
    book_id,
    books,
    tfidf_matrix,
    top_k=5
):
    books = books.copy()

    books["book_id"] = books["book_id"].astype(str)
    books["work_id"] = books["work_id"].astype(str)

    book_id = str(book_id)

    matches = books.index[
        books["book_id"] == book_id
    ].tolist()

    if not matches:
        raise ValueError(
            f"Không tìm thấy book_id: {book_id}"
        )

    book_index = matches[0]

    query_vector = tfidf_matrix[book_index]

    similarities = cosine_similarity(
        query_vector,
        tfidf_matrix
    ).flatten()

    similarities[book_index] = -1

    ranked_indices = similarities.argsort()[::-1]

    results = []
    used_work_ids = set()

    for index in ranked_indices:
        candidate = books.iloc[index]
        work_id = candidate["work_id"]

        if work_id in used_work_ids:
            continue

        results.append({
            "book_id": candidate["book_id"],
            "work_id": work_id,
            "title": candidate["title"],
            "authors": candidate["authors"],
            "average_rating": candidate["average_rating"],
            "similarity": similarities[index]
        })

        used_work_ids.add(work_id)

        if len(results) == top_k:
            break

    return pd.DataFrame(results)

# =========================
# Gợi ý sách cho một user
# =========================
def recommend_for_user(
    user_book_ids,
    books,
    tfidf_matrix,
    top_k=10
):
    books = books.copy()
    books["book_id"] = books["book_id"].astype(str)
    books["work_id"] = books["work_id"].astype(str)

    seen_book_ids = set(
        str(book_id)
        for book_id in user_book_ids
    )

    # Tìm index của các sách user đã tương tác
    profile_indices = books.index[
        books["book_id"].isin(seen_book_ids)
    ].tolist()

    if not profile_indices:
        return pd.DataFrame()

    # Vector đại diện cho sở thích của user
    user_profile = np.asarray(
        tfidf_matrix[profile_indices].mean(axis=0)
    )

    # Tính similarity giữa user profile và toàn bộ sách
    similarities = cosine_similarity(
        user_profile,
        tfidf_matrix
    ).flatten()

    # Không đề xuất sách user đã xem
    for index in profile_indices:
        similarities[index] = -1

    ranked_indices = similarities.argsort()[::-1]

    # Chọn Top-K, đồng thời loại trùng work
    results = []
    used_work_ids = set()

    for index in ranked_indices:
        candidate = books.iloc[index]
        work_id = candidate["work_id"]

        if work_id in used_work_ids:
            continue

        results.append({
            "book_id": candidate["book_id"],
            "work_id": work_id,
            "title": candidate["title"],
            "authors": candidate["authors"],
            "average_rating": candidate["average_rating"],
            "similarity": similarities[index]
        })

        used_work_ids.add(work_id)

        if len(results) == top_k:
            break

    return pd.DataFrame(results)


# =========================
# Tạo user profile matrix dạng sparse
# =========================
def build_user_profiles(
    train_df,
    books,
    tfidf_matrix,
    history_ratio=None,
    recency_decay=None
):
    books = books.copy()
    books["book_id"] = books["book_id"].astype(str)

    train_df = train_df.copy()
    train_df["user_id"] = train_df["user_id"].astype(str)
    train_df["book_id"] = train_df["book_id"].astype(str)

    if history_ratio is not None:
        train_df = train_df.sort_values(["user_id", "timestamp"]).copy()

        history_size = (
            train_df.groupby("user_id")["user_id"]
            .transform("size")
        )

        rank_from_end = (
            train_df.groupby("user_id")
            .cumcount(ascending=False)
        )

        keep_count = np.maximum(
            2,
            np.ceil(history_size * history_ratio).astype(int)
        )

        train_df = train_df[rank_from_end < keep_count].copy()

    # Mapping book_id -> index trong TF-IDF matrix
    book_index = pd.Series(
        books.index,
        index=books["book_id"]
    )

    # Giữ các interaction có book tồn tại trong metadata
    train_df = train_df[
        train_df["book_id"].isin(book_index.index)
    ].copy()

    # Mapping user_id -> row index
    user_ids = train_df["user_id"].drop_duplicates().tolist()

    user_index = pd.Series(
        range(len(user_ids)),
        index=user_ids
    )

    # =========================
    # Tính trọng số recency
    # =========================
    train_df = train_df.sort_values(
        ["user_id", "timestamp"]
    ).copy()

    position_from_latest = (
        train_df
        .groupby("user_id")
        .cumcount(ascending=False)
    )

    weights = 0.8 ** position_from_latest.to_numpy()

    # Sắp xếp theo thời gian
    train_df = train_df.sort_values(
        ["user_id", "timestamp"]
    ).copy()

    # =========================
    # Tính trọng số recency
    # =========================
    if recency_decay is None:
        weights = np.ones(len(train_df))
    else:
        position_from_latest = (
            train_df
            .groupby("user_id")
            .cumcount(ascending=False)
        )

        weights = (
            recency_decay ** position_from_latest.to_numpy()
        )

    # Chuyển interaction thành row/column
    rows = train_df["user_id"].map(
        user_index
    ).to_numpy()

    cols = train_df["book_id"].map(
        book_index
    ).to_numpy()

    # =========================
    # Tạo sparse user-item matrix
    # =========================
    from scipy.sparse import csr_matrix

    user_item = csr_matrix(
        (
            weights,
            (rows, cols)
        ),
        shape=(
            len(user_ids),
            len(books)
        )
    )

    # =========================
    # User profile
    # =========================
    weight_sums = np.asarray(
        user_item.sum(axis=1)
    ).ravel()

    weight_sums[
        weight_sums == 0
    ] = 1

    user_profiles = (
        user_item @ tfidf_matrix
    )

    user_profiles = user_profiles.multiply(
        1 / weight_sums[:, None]
    ).tocsr()

    # Chuẩn hóa L2 để có thể dùng linear kernel
    user_profiles = normalize(
        user_profiles,
        norm="l2",
        axis=1,
        copy=False
    )

    return user_ids, user_profiles


# =========================
# Recommendation theo batch user
# =========================
def recommend_users_in_batches(
    user_ids,
    user_profiles,
    books,
    tfidf_matrix,
    seen_books,
    batch_size=128,
    candidate_k=500,
    top_k=10
):
    books = books.copy()
    books["book_id"] = books["book_id"].astype(str)
    books["work_id"] = books["work_id"].astype(str)

    book_ids = books["book_id"].tolist()
    work_ids = books["work_id"].tolist()

    results = {}

    # =========================
    # Xử lý từng batch
    # =========================
    for start in range(0, len(user_ids), batch_size):
        end = min(
            start + batch_size,
            len(user_ids)
        )

        batch_user_ids = user_ids[start:end]
        batch_profiles = user_profiles[start:end]

        # =========================
        # Similarity bằng linear kernel
        # User profile và TF-IDF
        # đều đã L2-normalized
        # =========================
        similarities = linear_kernel(
            batch_profiles,
            tfidf_matrix
        )

        # =========================
        # Xử lý từng user
        # =========================
        for row_index, user_id in enumerate(batch_user_ids):
            scores = similarities[row_index]

            candidate_count = min(
                candidate_k,
                len(scores)
            )

            # Lấy candidate_k điểm cao nhất
            candidate_indices = scores.argpartition(
                -candidate_count
            )[-candidate_count:]

            # Sắp xếp chỉ nhóm candidate
            candidate_indices = candidate_indices[
                scores[candidate_indices].argsort()[::-1]
            ]

            user_seen = seen_books.get(
                user_id,
                set()
            )

            recommendations = []
            used_work_ids = set()

            for index in candidate_indices:
                book_id = book_ids[index]
                work_id = work_ids[index]

                if book_id in user_seen:
                    continue

                if work_id in used_work_ids:
                    continue

                recommendations.append(book_id)
                used_work_ids.add(work_id)

                if len(recommendations) == top_k:
                    break

            results[user_id] = recommendations

        print(
            f"Processed users: {end}/{len(user_ids)}"
        )

    return results


# =========================
# Recommendation bằng
# max similarity từ history
# =========================
def recommend_users_by_max_history(
    train_df,
    books,
    tfidf_matrix,
    seen_books,
    history_ratio=0.25,
    decay=0.7,
    batch_size=32,
    candidate_k=500,
    top_k=10
):
    books = books.copy()
    books["book_id"] = books["book_id"].astype(str)
    books["work_id"] = books["work_id"].astype(str)

    train_df = train_df.copy()
    train_df["user_id"] = train_df["user_id"].astype(str)
    train_df["book_id"] = train_df["book_id"].astype(str)

    train_df = train_df.sort_values(
        ["user_id", "timestamp"]
    )

    book_index = pd.Series(
        books.index,
        index=books["book_id"]
    )

    train_df = train_df[
        train_df["book_id"].isin(book_index.index)
    ].copy()

    user_ids = (
        train_df["user_id"]
        .drop_duplicates()
        .tolist()
    )

    # =====================================
    # Lấy cùng history 25% gần nhất như R07
    # =====================================
    histories = []

    for user_id, user_df in train_df.groupby(
        "user_id",
        sort=False
    ):
        history_size = len(user_df)

        keep_count = max(
            2,
            int(np.ceil(history_size * history_ratio))
        )

        recent = user_df.tail(
            keep_count
        ).iloc[::-1]

        user_history = []

        for age, (_, row) in enumerate(
            recent.iterrows()
        ):
            book_id = row["book_id"]

            index = book_index[book_id]

            weight = decay ** age

            user_history.append(
                (index, weight)
            )

        histories.append(user_history)

    book_ids = books["book_id"].tolist()
    work_ids = books["work_id"].tolist()

    results = {}

    # =====================================
    # Xử lý theo batch user
    # =====================================
    for start in range(
        0,
        len(user_ids),
        batch_size
    ):
        end = min(
            start + batch_size,
            len(user_ids)
        )

        batch_user_ids = user_ids[start:end]
        batch_histories = histories[start:end]

        # Các query history của batch
        query_indices = []
        query_weights = []
        query_users = []

        for local_user_idx, history in enumerate(
            batch_histories
        ):
            for book_index_value, weight in history:
                query_indices.append(
                    book_index_value
                )
                query_weights.append(weight)
                query_users.append(local_user_idx)

        history_vectors = tfidf_matrix[
            query_indices
        ]

        similarities = linear_kernel(
            history_vectors,
            tfidf_matrix
        )

        similarities *= np.asarray(
            query_weights
        )[:, None]

        # =====================================
        # Với mỗi user:
        # score(candidate) = max(weight * sim)
        # =====================================
        user_scores = {}

        for row_idx, local_user_idx in enumerate(
            query_users
        ):
            if local_user_idx not in user_scores:
                user_scores[local_user_idx] = {}

            scores = similarities[row_idx]

            candidate_count = min(
                candidate_k,
                len(scores)
            )

            candidate_indices = scores.argpartition(
                -candidate_count
            )[-candidate_count:]

            for index in candidate_indices:
                score = scores[index]

                previous = user_scores[
                    local_user_idx
                ].get(index, -np.inf)

                if score > previous:
                    user_scores[
                        local_user_idx
                    ][index] = score

        # =====================================
        # Tạo Top-K
        # =====================================
        for local_user_idx, user_id in enumerate(
            batch_user_ids
        ):
            scores_dict = user_scores.get(
                local_user_idx,
                {}
            )

            ranked = sorted(
                scores_dict.items(),
                key=lambda x: x[1],
                reverse=True
            )

            user_seen = seen_books.get(
                user_id,
                set()
            )

            recommendations = []
            used_work_ids = set()

            for index, score in ranked:
                book_id = book_ids[index]
                work_id = work_ids[index]

                if book_id in user_seen:
                    continue

                if work_id in used_work_ids:
                    continue

                recommendations.append(
                    book_id
                )

                used_work_ids.add(
                    work_id
                )

                if len(recommendations) == top_k:
                    break

            results[user_id] = recommendations

        print(
            f"Processed users: "
            f"{end}/{len(user_ids)}"
        )

    return results

# =========================
# Recommendation Tool - TF-IDF R07
# =========================
def recommend_for_user_r07(
    user_history_df,
    books,
    tfidf_matrix,
    history_ratio=0.25,
    recency_decay=0.7,
    top_k=10
):
    books = books.copy()

    books["book_id"] = books["book_id"].astype(str)
    books["work_id"] = books["work_id"].astype(str)

    history = user_history_df.copy()

    history["book_id"] = history["book_id"].astype(str)

    # Chỉ giữ sách có trong metadata
    book_index = pd.Series(
        books.index,
        index=books["book_id"]
    )

    history = history[
        history["book_id"].isin(book_index.index)
    ].copy()

    if history.empty:
        return pd.DataFrame()

    # =========================
    # Lấy 25% lịch sử gần nhất
    # =========================
    history = history.sort_values(
        "timestamp"
    )

    keep_count = max(
        2,
        int(
            np.ceil(
                len(history) * history_ratio
            )
        )
    )

    recent_history = history.tail(
        keep_count
    ).iloc[::-1].copy()

    # =========================
    # Weighted user profile
    # newest = 1
    # older = 0.7, 0.7^2, ...
    # =========================
    profile_indices = [
        book_index[book_id]
        for book_id in recent_history["book_id"]
    ]

    weights = np.array([
        recency_decay ** age
        for age in range(len(profile_indices))
    ])

    history_vectors = tfidf_matrix[
        profile_indices
    ]

    weighted_profile = (
        history_vectors.multiply(
            weights[:, None]
        ).sum(axis=0)
        / weights.sum()
    )

    weighted_profile = np.asarray(
        weighted_profile
    ).reshape(1, -1)

    weighted_profile = normalize(
        weighted_profile,
        norm="l2",
        axis=1
    )

    # =========================
    # Score toàn bộ sách
    # =========================
    similarities = linear_kernel(
        weighted_profile,
        tfidf_matrix
    ).flatten()

    # =========================
    # Không đề xuất bất kỳ sách
    # user đã từng tương tác
    # =========================
    seen_book_ids = set(
        history["book_id"]
    )

    for book_id in seen_book_ids:
        index = book_index[book_id]
        similarities[index] = -1

    # =========================
    # Ranking
    # =========================
    ranked_indices = similarities.argsort()[::-1]

    results = []
    used_work_ids = set()

    for index in ranked_indices:

        candidate = books.iloc[index]

        book_id = candidate["book_id"]
        work_id = candidate["work_id"]

        if book_id in seen_book_ids:
            continue

        if work_id in used_work_ids:
            continue

        results.append({
            "book_id": book_id,
            "work_id": work_id,
            "title": candidate["title"],
            "authors": candidate["authors"],
            "average_rating": candidate["average_rating"],
            "similarity": float(
                similarities[index]
            ),
        })

        used_work_ids.add(work_id)

        if len(results) == top_k:
            break

    return pd.DataFrame(results)