import numpy as np
import pandas as pd

from surprise import Dataset, Reader, SVD, SVDpp


def train_svd(
    train_file,
    n_factors=100,
    n_epochs=20,
    lr_all=0.005,
    reg_all=0.02,
    random_state=42,
):
    df = pd.read_csv(train_file)

    df["user_id"] = df["user_id"].astype(str)
    df["book_id"] = df["book_id"].astype(str)
    
    reader = Reader(rating_scale=(1, 5))

    data = Dataset.load_from_df(
        df[["user_id", "book_id", "rating"]],
        reader,
    )

    trainset = data.build_full_trainset()

    model = SVD(
        n_factors=n_factors,
        n_epochs=n_epochs,
        lr_all=lr_all,
        reg_all=reg_all,
        random_state=random_state,
    )

    model.fit(trainset)

    return model

def train_svdpp(
    train_file,
    n_factors=50,
    n_epochs=20,
    lr_all=0.005,
    reg_all=0.02,
    random_state=42
):
    train_df = pd.read_csv(
        train_file,
        dtype={
            "user_id": str,
            "book_id": str
        }
    )

    reader = Reader(
        rating_scale=(1, 5)
    )

    data = Dataset.load_from_df(
        train_df[
            ["user_id", "book_id", "rating"]
        ],
        reader
    )

    trainset = data.build_full_trainset()

    model = SVDpp(
        n_factors=n_factors,
        n_epochs=n_epochs,
        lr_all=lr_all,
        reg_all=reg_all,
        random_state=random_state,
        cache_ratings=True
    )

    model.fit(trainset)

    return model

def predict_score(model, user_id, book_id):
    prediction = model.predict(user_id, book_id)
    return prediction.est


def recommend_for_user(
    model,
    user_id,
    books,
    train_df,
    top_k=10,
):
    """
    Generate Top-K recommendations for one user.

    Excludes:
    - books already seen by the user
    - duplicate works (work_id)
    """

    if user_id not in model.trainset._raw2inner_id_users:
        return []

    # Books that can actually be scored by SVD.
    known_books = set(
        str(book_id)
        for book_id in model.trainset._raw2inner_id_items.keys()
    )

    candidates = books[
        books["book_id"].astype(str).isin(known_books)
    ].copy()

    # Remove books already seen in training.
    seen_books = set(
        train_df.loc[
            train_df["user_id"].astype(str) == str(user_id),
            "book_id",
        ].astype(str)
    )

    candidates = candidates[
        ~candidates["book_id"].astype(str).isin(seen_books)
    ].copy()

    if candidates.empty:
        return []

    inner_uid = model.trainset.to_inner_uid(user_id)

    user_vector = model.pu[inner_uid]
    user_bias = model.bu[inner_uid]

    item_inner_ids = []
    valid_rows = []

    for index, row in candidates.iterrows():
        book_id = str(row["book_id"])

        if book_id not in model.trainset._raw2inner_id_items:
            continue

        inner_iid = model.trainset.to_inner_iid(book_id)

        item_inner_ids.append(inner_iid)
        valid_rows.append(index)

    if not valid_rows:
        return []

    item_factors = model.qi[item_inner_ids]
    item_biases = model.bi[item_inner_ids]

    scores = (
        model.trainset.global_mean
        + user_bias
        + item_biases
        + item_factors @ user_vector
    )

    candidates = candidates.loc[valid_rows].copy()
    candidates["score"] = scores

    # Highest predicted score first.
    candidates = candidates.sort_values(
        "score",
        ascending=False,
    )

    # Prevent multiple editions of the same work
    # from filling the Top-K.
    recommendations = []
    used_works = set()

    for _, row in candidates.iterrows():
        work_id = str(row["work_id"])

        if work_id in used_works:
            continue

        used_works.add(work_id)

        recommendations.append(
            {
                "book_id": str(row["book_id"]),
                "score": float(row["score"]),
            }
        )

        if len(recommendations) == top_k:
            break

    return recommendations

def recommend_users_svdpp(
    model,
    user_ids,
    books,
    seen_books,
    batch_size=128,
    candidate_k=500,
    top_k=10
):
    books = books.copy()

    books["book_id"] = (
        books["book_id"]
        .astype(str)
    )

    books["work_id"] = (
        books["work_id"]
        .astype(str)
    )

    book_ids = (
        books["book_id"]
        .to_numpy()
    )

    work_ids = (
        books["work_id"]
        .to_numpy()
    )

    trainset = model.trainset

    # book_id -> inner item index
    item_index = {}

    for raw_iid, inner_iid in (
        trainset._raw2inner_id_items.items()
    ):
        item_index[str(raw_iid)] = inner_iid

    # Chỉ giữ những sách model biết
    valid_indices = np.array([
        i
        for i, book_id in enumerate(book_ids)
        if book_id in item_index
    ])

    valid_book_ids = book_ids[valid_indices]
    valid_work_ids = work_ids[valid_indices]

    inner_item_indices = np.array([
        item_index[book_id]
        for book_id in valid_book_ids
    ])

    # Item latent factors + item bias
    qi = model.qi[
        inner_item_indices
    ]

    bi = model.bi[
        inner_item_indices
    ]

    results = {}

    for start in range(
        0,
        len(user_ids),
        batch_size
    ):
        end = min(
            start + batch_size,
            len(user_ids)
        )

        batch_user_ids = user_ids[
            start:end
        ]

        # ---------------------------------
        # Tạo user vector SVD++
        # ---------------------------------

        user_vectors = []

        for user_id in batch_user_ids:

            try:
                inner_uid = (
                    trainset
                    .to_inner_uid(user_id)
                )
            except ValueError:
                user_vectors.append(
                    np.zeros(
                        model.n_factors,
                        dtype=np.float32
                    )
                )
                continue

            rated_items = [
                inner_iid
                for inner_iid, _ in
                trainset.ur[inner_uid]
            ]

            if rated_items:
                implicit_vector = (
                    model.yj[rated_items]
                    .sum(axis=0)
                    / np.sqrt(len(rated_items))
                )
            else:
                implicit_vector = np.zeros(
                    model.n_factors,
                    dtype=model.yj.dtype
                )

            user_vector = (
                model.pu[inner_uid]
                + implicit_vector
            )

            user_vectors.append(
                user_vector
            )

        user_vectors = np.asarray(
            user_vectors
        )

        # ---------------------------------
        # Score tất cả candidate books
        # ---------------------------------

        scores_matrix = (
            user_vectors @ qi.T
        )

        # bias
        inner_user_indices = []

        for user_id in batch_user_ids:
            try:
                inner_uid = (
                    trainset
                    .to_inner_uid(user_id)
                )
                inner_user_indices.append(
                    inner_uid
                )
            except ValueError:
                inner_user_indices.append(
                    None
                )

        for row_index, inner_uid in enumerate(
            inner_user_indices
        ):
            if inner_uid is not None:
                scores_matrix[row_index] += (
                    model.trainset.global_mean
                    + model.bu[inner_uid]
                    + bi
                )
            else:
                scores_matrix[row_index] = (
                    model.trainset.global_mean + bi
                )

        # ---------------------------------
        # Ranking từng user
        # ---------------------------------

        for row_index, user_id in enumerate(
            batch_user_ids
        ):
            scores = scores_matrix[
                row_index
            ].copy()

            user_seen = seen_books.get(
                user_id,
                set()
            )

            # loại sách đã xem
            for local_index, book_id in enumerate(
                valid_book_ids
            ):
                if book_id in user_seen:
                    scores[local_index] = -np.inf

            candidate_count = min(
                candidate_k,
                len(scores)
            )

            candidate_indices = (
                scores.argpartition(
                    -candidate_count
                )[-candidate_count:]
            )

            candidate_indices = (
                candidate_indices[
                    scores[candidate_indices]
                    .argsort()[::-1]
                ]
            )

            recommendations = []
            used_work_ids = set()

            for index in candidate_indices:

                work_id = (
                    valid_work_ids[index]
                )

                if work_id in used_work_ids:
                    continue

                recommendations.append(
                    valid_book_ids[index]
                )

                used_work_ids.add(work_id)

                if len(recommendations) == top_k:
                    break

            results[user_id] = recommendations

        print(
            f"Processed users: "
            f"{end}/{len(user_ids)}"
        )

    return results