# NGHIÊN CỨU MÔ HÌNH GỢI Ý DỰA TRÊN NỘI DUNG CHO BÀI TOÁN ĐỀ XUẤT SÁCH CÁ NHÂN HÓA

## 1. Cấu trúc project

```text
Catbook/
├── admin/                          # Trang quản trị hệ thống (banners, categories, news, orders, payment_settings, products, site_settings, users)
├── docs/                           # Báo cáo kết quả nghiên cứu (BAO_CAO_NGHIEN_CUU.md) và các file JSON tổng hợp kết quả thực nghiệm
├── tools/                          # Các script PHP hỗ trợ nhập dữ liệu sách, tạo ảnh bìa và đồng bộ đánh giá
├── uploads/                        # Hình ảnh sản phẩm, banner, tin tức và nhận diện thương hiệu
├── recommendation_research/        # Mô-đun nghiên cứu và vận hành hệ thống gợi ý cá nhân hóa (Python)
│   ├── artifacts/                  # Vectorizer và ma trận TF-IDF đã huấn luyện (book_ids.csv, book_tfidf.npz, tfidf_vectorizer.joblib)
│   ├── configs/                    # Cấu hình tham số mô hình (content_based.yaml)
│   ├── experiments/                # Kịch bản thực nghiệm các mô hình (collaborative, content_based, data, popularity)
│   ├── recommendation/             # Thư viện lõi xử lý dữ liệu, đánh giá và thuật toán (collaborative, content_based, hybrid, popularity, semantic)
│   ├── results/                    # Các file CSV lưu kết quả thực nghiệm trên Goodreads và CatBook
│   ├── tests/                      # Kiểm thử pipeline gợi ý
│   ├── bootstrap_catbook.py        # Khởi tạo dữ liệu huấn luyện cho CatBook
│   ├── catbook_semantic.py         # Tính toán đặc trưng ngữ nghĩa cho dữ liệu CatBook
│   ├── create_mock_users.py        # Tạo người dùng giả lập để kiểm thử
│   ├── evaluate_catbook.py         # Đánh giá mô hình gợi ý trên dữ liệu CatBook
│   ├── run_offline_recommendation.py # Pipeline tính toán gợi ý theo lô và ghi vào MySQL
│   ├── run_recommendation.bat      # Script chạy tự động cho Windows Task Scheduler
│   ├── summarize_goodreads_results.py # Tổng hợp số liệu thực nghiệm Goodreads
│   ├── requirements.txt            # Danh sách thư viện cho môi trường nghiên cứu
│   └── requirements-web.txt        # Danh sách thư viện cho pipeline tích hợp website
├── catbook_db.sql                  # File cấu trúc và dữ liệu mẫu cơ sở dữ liệu MySQL
├── config.php                      # Cấu hình kết nối CSDL và các hàm xử lý cốt lõi
├── index.php                       # Trang chủ hiển thị sách và danh sách gợi ý cá nhân hóa
├── products.php, product_detail.php, search_suggest.php # Hiển thị, tìm kiếm và xem chi tiết sản phẩm
├── cart.php, checkout.php, payment.php, orders.php, ajax_get_order.php # Giỏ hàng, đặt hàng, thanh toán và quản lý đơn hàng
├── login.php, register.php, logout.php, profile.php # Đăng ký, đăng nhập và quản lý thông tin tài khoản
├── news.php, news_detail.php       # Tin tức
├── chatbot.php, chatbot_widget.php # Chatbot hỗ trợ tìm kiếm sách và giải đáp tự động
└── README.md                       # Tài liệu mô tả cấu trúc project, hướng dẫn cài đặt và triển khai
```

## 2. Cài đặt và chạy website

1. Giải nén thư mục `Catbook` vào `htdocs` của XAMPP.
   Ví dụ: `D:\xampp\htdocs\Catbook`

2. Mở phpMyAdmin và import file:
   `catbook_db.sql`

3. Cấu hình kết nối CSDL trong `config.php` nếu máy bạn không dùng tài khoản mặc định:
   - DB name: `catbook_db`
   - User: `root`
   - Password: để trống

4. Chạy website:
   `http://localhost/Catbook/`

5. Tài khoản mẫu:
   - Admin: `admin` / `admin123`
   - User: `userdemo` / `user123`

## 3. Chạy hệ thống gợi ý cá nhân hóa (Python, xử lý theo lô)

- Mở terminal tại thư mục `recommendation_research`.
- Tạo môi trường riêng cho pipeline:
  ```bash
  py -3.11 -m venv .venv-web
  ```
- Kích hoạt môi trường và cài các thư viện cần cho pipeline:
  ```bash
  .venv-web\Scripts\activate
  pip install -r requirements-web.txt
  ```
- Chạy:
  ```bash
  python run_offline_recommendation.py
  ```
- Mặc định dùng MySQL trên `127.0.0.1:3306`, database `catbook_db`, user `root` và mật khẩu trống. Có thể cấu hình bằng các biến môi trường: `CATBOOK_DB_HOST`, `CATBOOK_DB_PORT`, `CATBOOK_DB_NAME`, `CATBOOK_DB_USER`, `CATBOOK_DB_PASSWORD`.
- Script đọc sách còn hàng (thuộc danh mục đang bật), đơn hàng chưa hủy, lượt thêm giỏ, lượt xem và đánh giá từ 3 sao; mỗi cặp người dùng–sách có trọng số tối đa 5; tài khoản admin bị loại. Giữ thuật toán TF-IDF của nghiên cứu và ghi Top 10 vào bảng `user_recommendations`. Website dùng kết quả gần nhất và hiển thị sách phổ biến khi tài khoản chưa có gợi ý.
- Có thể lên lịch chạy hằng ngày bằng Windows Task Scheduler. Dùng đường dẫn đầy đủ tới `recommendation_research\.venv-web\Scripts\python.exe` làm chương trình, `run_offline_recommendation.py` làm đối số và `recommendation_research` làm thư mục bắt đầu.
- Tạo người dùng giả lập (mock users) với sở thích khác nhau để nghiệm thu:
  ```bash
  python create_mock_users.py
  # Xóa chúng:
  python create_mock_users.py --remove
  ```
  Script tạo user `mock_cat<id>_<n>`, chạy pipeline và ghi báo cáo vào `mock_users_report.json`. Thêm `--password-hash <hash>` để đăng nhập được. Tạo hash bằng:
  ```bash
  php -r "echo password_hash('mock123', PASSWORD_DEFAULT);"
  ```
- Tùy chọn gợi ý:
  - `--user-id <id>`: chỉ tính lại một người dùng.
  - `--method tfidf | semantic | hybrid`: `tfidf` (mặc định) | `semantic` | `hybrid` (cần `fastembed`, tải mô hình lần đầu).
  - Biến `CATBOOK_SEGMENT=1`: bật tách từ tiếng Việt (cần `pyvi`).
- Website tự gọi pipeline khi người dùng xem sách, thêm vào giỏ hoặc đặt hàng (bảng `product_cart_events` lưu lượt thêm vào giỏ). Đường dẫn Python lấy từ biến `CATBOOK_PYTHON` hoặc file `recommendation_research\python_path.txt`.
- File `run_recommendation.bat` dùng cho Task Scheduler. Tác vụ "CatBook Recommendation Nightly" (chạy 02:00 hằng ngày) đã được tạo; log: `run_offline_recommendation.log`, và `web_refresh.log` cho các lần website tự làm mới.
- Báo cáo kết quả nghiên cứu: `docs\BAO_CAO_NGHIEN_CUU.md`; tạo lại số liệu: `summarize_goodreads_results.py` và `evaluate_catbook.py`.
