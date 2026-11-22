import numpy as np
import pandas as pd


def build_book_index(books):
    return {
        str(book_id): index
        for index, book_id
        in enumerate(books["book_id"])
    }


def build_user_profiles(
    train_df,
    book_index,
    book_embeddings,
    history_ratio=0.25,
    decay=0.7,
):
    profiles = []
    user_ids = []

    train_df = train_df.sort_values(
        ["user_id", "timestamp"]
    )

    for user_id, history in train_df.groupby(
        "user_id",
        sort=False,
    ):
        keep_count = max(
            2,
            int(
                np.ceil(
                    len(history) * history_ratio
                )
            ),
        )

        history = (
            history
            .tail(keep_count)
            .iloc[::-1]
        )

        indices = []
        weights = []

        for age, row in enumerate(
            history.itertuples()
        ):
            index = book_index.get(
                str(row.book_id)
            )

            if index is None:
                continue

            indices.append(index)
            weights.append(decay ** age)

        if not indices:
            continue

        vectors = book_embeddings[indices]

        profile = np.average(
            vectors,
            axis=0,
            weights=np.asarray(
                weights,
                dtype=np.float32,
            ),
        )

        norm = np.linalg.norm(profile)

        if norm == 0:
            continue

        profile = (
            profile / norm
        ).astype(np.float32)

        profiles.append(profile)
        user_ids.append(user_id)

    return (
        np.asarray(
            user_ids,
            dtype=str,
        ),
        np.asarray(
            profiles,
            dtype=np.float32,
        ),
    )


def generate_recommendations(
    books,
    train_df,
    book_embeddings,
    history_ratio=0.25,
    decay=0.7,
    candidate_k=500,
    top_k=10,
    batch_size=128,
):
    book_index = build_book_index(
        books
    )

    # Đảm bảo book embeddings chuẩn hóa
    norms = np.linalg.norm(
        book_embeddings,
        axis=1,
        keepdims=True,
    )

    book_embeddings = (
        book_embeddings
        / np.clip(norms, 1e-12, None)
    )

    user_ids, user_profiles = (
        build_user_profiles(
            train_df=train_df,
            book_index=book_index,
            book_embeddings=book_embeddings,
            history_ratio=history_ratio,
            decay=decay,
        )
    )

    seen_books = (
        train_df
        .groupby("user_id")["book_id"]
        .apply(set)
        .to_dict()
    )

    work_ids = (
        books["work_id"]
        .astype(str)
        .to_numpy()
    )

    book_ids = (
        books["book_id"]
        .to_numpy()
    )

    results = []

    for start in range(
        0,
        len(user_ids),
        batch_size,
    ):
        end = min(
            start + batch_size,
            len(user_ids),
        )

        batch_profiles = (
            user_profiles[start:end]
        )

        # Vì cả profile và book embedding
        # đều L2-normalized:
        # dot product = cosine similarity
        scores_matrix = (
            batch_profiles
            @ book_embeddings.T
        )

        for row_index, user_id in enumerate(
            user_ids[start:end]
        ):
            scores = (
                scores_matrix[row_index]
                .copy()
            )

            for book_id in seen_books.get(
                user_id,
                set(),
            ):
                index = book_index.get(
                    str(book_id)
                )

                if index is not None:
                    scores[index] = -np.inf

            candidate_count = min(
                candidate_k,
                len(scores),
            )

            candidate_indices = (
                np.argpartition(
                    scores,
                    -candidate_count,
                )[-candidate_count:]
            )

            candidate_indices = (
                candidate_indices[
                    np.argsort(
                        scores[
                            candidate_indices
                        ]
                    )[::-1]
                ]
            )

            used_work_ids = set()
            rank = 0

            for index in candidate_indices:
                work_id = work_ids[index]

                if work_id in used_work_ids:
                    continue

                rank += 1

                results.append(
                    {
                        "user_id": user_id,
                        "book_id": book_ids[index],
                        "rank": rank,
                    }
                )

                used_work_ids.add(
                    work_id
                )

                if rank == top_k:
                    break

    return pd.DataFrame(results)