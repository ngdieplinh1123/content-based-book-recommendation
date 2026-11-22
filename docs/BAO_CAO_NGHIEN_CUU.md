# Kết quả nghiên cứu hệ thống gợi ý CatBook

Số liệu được sinh bằng các script chỉ đọc dữ liệu, không sửa mô hình nghiên cứu:
`recommendation_research\summarize_goodreads_results.py`,
`recommendation_research\evaluate_catbook.py` và
`recommendation_research\bootstrap_catbook.py`
(file thô: `ket_qua_goodreads.json`, `ket_qua_catbook.json`,
`results\catbook_trials.csv`, `results\catbook_bootstrap.csv`).

## 1. Goodreads Poetry (60.104 người dùng kiểm thử, 1 sách ẩn mỗi người)

Dữ liệu sau lọc 5-core: 60.104 người dùng, 17.551 sách, 816.977 tương tác.
Chia theo thứ tự thời gian của từng người dùng (leave-last-out): train 732.031,
validation 24.842 (người dùng có từ 10 tương tác), test 60.104.

| Mô hình | Hit Rate@10 | NDCG@10 |
|---|---|---|
| **Sách phổ biến nhất (popularity)** | **0,1299** | **0,0756** |
| TF-IDF + recency (R07, hồ sơ trung bình) | 0,0582 | 0,0280 |
| TF-IDF + recency (R07, Max-History) | 0,0548 | 0,0265 |
| Hybrid (RRF: TF-IDF + Jina, Top-100) | 0,0460 | 0,0233 |
| Jina semantic embedding (R07) | 0,0284 | 0,0144 |
| SVD++ (100 nhân tố, 40 epoch) | 0,0048 | 0,0026 |
| SVD (100 nhân tố, 40 epoch) | 0,0034 | 0,0017 |

**Phát hiện chính**

- Gợi ý "sách phổ biến nhất" cho Hit Rate@10 cao hơn khoảng 2,2 lần TF-IDF, 4,6 lần
  embedding ngữ nghĩa và khoảng 27–38 lần SVD++/SVD trên dữ liệu Goodreads này.
- Mục tiêu của tập kiểm thử tập trung vào sách phổ biến: 1% sách phổ biến nhất chiếm
  36,38% mục tiêu và 10% sách phổ biến nhất chiếm 66,58%. Đây là căn cứ giải thích
  vì sao Popularity khó bị vượt trên tập này.
- Các cấu hình ưu tiên lịch sử gần đây (chỉ dùng 25% lịch sử mới nhất, suy giảm 0,7)
  cải thiện rõ trên validation, cho thấy sở thích thay đổi theo thời gian.
- Dùng sách giống nhất trong lịch sử (Max-History) thay vì hồ sơ trung bình không
  cải thiện kết quả (thấp hơn khoảng 5–6%).
- SVD/SVD++ (thư viện Surprise) được huấn luyện để dự đoán điểm đánh giá rồi xếp hạng
  theo điểm dự đoán, nên không trực tiếp tối ưu cho bài toán dự đoán sách tiếp theo;
  kết quả thấp của chúng không dùng để kết luận chung về lọc cộng tác.
- Hệ quả thiết kế: CatBook có phương án dự phòng (danh sách sách phổ biến) cho người
  dùng chưa có lịch sử.

## 2. CatBook (leave-one-book-out, Top-10, 56 người dùng có ≥ 3 sách, 345 lượt thử)

| Phương pháp | Hit Rate@10 [KTC 95%] | NDCG@10 | Cùng danh mục |
|---|---|---|---|
| Ngẫu nhiên | 0,0957 [0,0655; 0,1281] | 0,0403 | 0,1499 |
| Phổ biến nhất | 0,2116 [0,1579; 0,2689] | 0,0786 | 0,1606 |
| **TF-IDF (fit lại trên CatBook)** | **0,3014** [0,2274; 0,3858] | 0,1377 | **0,4023** |
| TF-IDF + tách từ (pyvi) | 0,2870 [0,2147; 0,3614] | 0,1341 | 0,3838 |
| Semantic embedding đa ngôn ngữ | 0,2145 [0,1600; 0,2722] | 0,1037 | 0,3351 |
| Hybrid (TF-IDF + embedding, RRF) | 0,2841 [0,2107; 0,3620] | **0,1433** | 0,3794 |

Khoảng tin cậy (KTC) 95% tính bằng bootstrap theo người dùng (5.000 lần, seed 42).

**Chênh lệch có ý nghĩa thống kê (KTC 95% của hiệu không chứa 0)**

- TF-IDF so với Semantic: Hit Rate@10 +0,087; NDCG@10 +0,034; cùng danh mục +0,067.
- TF-IDF so với Popularity: Hit Rate@10 +0,090; NDCG@10 +0,059; cùng danh mục +0,242.
- Hybrid so với Semantic: có ý nghĩa ở cả ba chỉ số.
- **TF-IDF so với Hybrid: không có ý nghĩa ở chỉ số nào** (Hit Rate@10 +0,017,
  KTC [−0,035; +0,071]; NDCG@10 −0,006, KTC [−0,035; +0,022]).

**Giải thích và lưu ý**

- Trên CatBook, gợi ý cá nhân hóa dựa trên nội dung vượt "phổ biến nhất", khác với
  Goodreads. Dữ liệu CatBook gồm người dùng mô phỏng có sở thích theo danh mục và
  catalog chỉ có 114 sách; thiết lập này thuận lợi cho phương pháp dựa trên từ khóa.
  Kết quả chỉ chứng minh luồng hệ thống hoạt động và dùng để chọn cấu hình ban đầu,
  không thay thế kết luận trên Goodreads.
- Embedding đa ngôn ngữ (paraphrase-multilingual-MiniLM-L12-v2, chạy cục bộ bằng
  `fastembed`) thấp hơn TF-IDF. Nguyên nhân chưa được kiểm chứng; một số khả năng là
  metadata ngắn, danh mục lặp trong văn bản, hoặc giới hạn độ dài đầu vào của mô hình.
- Embedding của Goodreads (Jina, tiếng Anh) không được dùng lại cho CatBook.
- Tách từ pyvi không cải thiện Hit Rate (0,3014 so với 0,2870, chưa kiểm định) nên để
  tùy chọn (`CATBOOK_SEGMENT=1`), mặc định tắt.

## 3. Cách chạy lại

```
python summarize_goodreads_results.py
python evaluate_catbook.py
python bootstrap_catbook.py
python run_offline_recommendation.py --method tfidf|semantic|hybrid
```