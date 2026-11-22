<?php
require_once 'header.php';

$statusMsg = '';
$statusType = '';

// Khởi tạo thư mục chứa ảnh nếu chưa có
$uploadDir = '../uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Xử lý Thêm, Sửa, Xóa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['name']);
            $category_id = (int)$_POST['category_id'];
            $price = str_replace(',', '', $_POST['price']);
            $discount_price = str_replace(',', '', $_POST['discount_price']);
            $quantity = (int)$_POST['quantity'];
            $description = trim($_POST['description']);
            $author = trim($_POST['author'] ?? '');
            $publisher = trim($_POST['publisher'] ?? '');
            $publicationYear = trim($_POST['publication_year'] ?? '');
            $publicationYear = $publicationYear === '' ? null : (int)$publicationYear;
            
            // Xử lý upload ảnh
            $imageName = $_POST['old_image'] ?? '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $imageName = time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
                }
            }

            if ($action === 'add' && !empty($name)) {
                $stmt = $pdo->prepare("INSERT INTO products (category_id, name, price, discount_price, quantity, image, description, author, publisher, publication_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$category_id, $name, $price, $discount_price, $quantity, $imageName, $description, $author, $publisher, $publicationYear]);
                $statusMsg = 'Thêm sản phẩm thành công!';
                $statusType = 'success';
            } elseif ($action === 'edit' && !empty($name)) {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE products SET category_id=?, name=?, price=?, discount_price=?, quantity=?, image=?, description=?, author=?, publisher=?, publication_year=? WHERE id=?");
                $stmt->execute([$category_id, $name, $price, $discount_price, $quantity, $imageName, $description, $author, $publisher, $publicationYear, $id]);
                $statusMsg = 'Cập nhật sản phẩm thành công!';
                $statusType = 'success';
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            if ($id > 0) {
                // Tùy chọn: Xóa ảnh cũ
                $stmtImg = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                $stmtImg->execute([$id]);
                $img = $stmtImg->fetchColumn();
                if ($img && file_exists($uploadDir . $img)) {
                    unlink($uploadDir . $img);
                }

                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $statusMsg = 'Đã xóa sản phẩm!';
                $statusType = 'success';
            }
        }
    } catch (\PDOException $e) {
        $statusMsg = 'Có lỗi xảy ra với cơ sở dữ liệu!';
        $statusType = 'error';
    }
}

// Lấy danh sách danh mục cho select box
$stmtCat = $pdo->query("SELECT id, name FROM categories WHERE status = 1");
$categoriesList = $stmtCat->fetchAll();

// Phân trang & Tìm kiếm
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = per_page(10);
$offset = ($page - 1) * $limit;

$whereClause = '';
$params = [];
if ($search !== '') {
    $whereClause = "WHERE (p.name LIKE ? OR p.author LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM products p $whereClause");
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    /* Custom lại Select2 cho hợp với giao diện Tailwind */
    .select2-container--default .select2-selection--single {
        height: 3rem;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #1e293b;
        font-weight: 500;
        padding-left: 1rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100%;
        right: 10px;
    }
    .select2-dropdown {
        border-color: #e2e8f0;
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .select2-search__field {
        border-radius: 0.5rem !important;
        border-color: #cbd5e1 !important;
    }
    .select2-results__option--highlighted[aria-selected] {
        background-color: #ca7344 !important;
    }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-4 sm:p-6 border-b border-slate-100 bg-slate-50/50">
        <div class="flex flex-row justify-between items-center gap-3">
            <form method="GET" action="" class="flex-1 max-w-md relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm sản phẩm..." class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-11 pr-4 text-slate-700 text-sm focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
            </form>
            <button onclick="openAddModal()" class="bg-brown-600 hover:bg-brown-700 text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-colors shadow-md shadow-brown-500/30 flex items-center shrink-0">
                <i class="fas fa-plus"></i><span class="hidden sm:inline sm:ml-2">Thêm Mới</span>
            </button>
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <?= per_page_selector($limit, $page, (int)$totalRecords) ?>
        <table class="w-full text-left border-collapse mobile-card-table">
            <thead>
                <tr>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-l-lg uppercase tracking-wider w-16">ID</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider w-24">Ảnh</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Tên Sản Phẩm</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Danh Mục</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Giá Bán</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider w-20 text-center">Kho</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-r-lg text-right uppercase tracking-wider w-40">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="7" class="py-12 text-center text-slate-400 font-medium">Không tìm thấy sản phẩm nào</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr class="hover:bg-slate-50 transition-colors group border-b border-slate-50">
                            <td data-label="ID" class="py-4 px-4 font-bold text-slate-800"><?= $p['id'] ?></td>
                            <td data-label="Ảnh" class="py-4 px-4">
                                <?php if ($p['image']): ?>
                                    <img src="<?= htmlspecialchars(product_image_url($p['image'], '../uploads/products/'), ENT_QUOTES, 'UTF-8') ?>" alt="Img" class="w-12 h-12 rounded-lg object-cover border border-slate-200">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 border border-slate-200"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Tên Sản Phẩm" class="py-4 px-4 font-bold text-brown-700">
                                <?= htmlspecialchars($p['name']) ?>
                                <?php if (!empty($p['author'])): ?><span class="block text-xs text-slate-500 font-normal">Tác giả: <?= htmlspecialchars($p['author']) ?></span><?php endif; ?>
                            </td>
                            <td data-label="Danh Mục" class="py-4 px-4 font-medium text-slate-600"><?= htmlspecialchars($p['category_name']) ?></td>
                            <td data-label="Giá Bán" class="py-4 px-4 font-black text-brown-600">
                                <?= number_format($p['discount_price'] > 0 ? $p['discount_price'] : $p['price'], 0, ',', ',') ?>đ
                                <?php if ($p['discount_price'] > 0): ?>
                                    <br><span class="text-xs text-slate-400 line-through font-normal"><?= number_format($p['price'], 0, ',', ',') ?>đ</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Kho" class="py-4 px-4 text-center font-bold text-slate-700"><?= $p['quantity'] ?></td>
                            <td data-label="Thao Tác" class="py-4 px-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='openEditModal(<?= json_encode($p) ?>)' class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm">
                                        <i class="fas fa-edit"></i><span class="hidden sm:inline sm:ml-1">Sửa</span>
                                    </button>
                                    <button onclick="confirmDelete(<?= $p['id'] ?>)" class="bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm">
                                        <i class="fas fa-trash-alt"></i><span class="hidden sm:inline sm:ml-1">Xóa</span>
                                    </button>
                                    <form id="deleteForm_<?= $p['id'] ?>" method="POST" action="" class="hidden">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex justify-center sm:justify-end">
                <div class="flex gap-1">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="<?= page_url($i) ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-bold text-sm transition-all <?= $page === $i ? 'bg-brown-600 text-white shadow-md shadow-brown-500/30' : 'bg-slate-50 text-slate-600 hover:bg-slate-200' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="productModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('productModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-2xl w-full border border-slate-100 max-h-[90vh] flex flex-col">
            <form method="POST" action="" enctype="multipart/form-data" class="flex flex-col h-full">
                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="id" id="product_id">
                <input type="hidden" name="old_image" id="old_image">

                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white shrink-0">
                    <h3 class="text-xl font-black text-slate-800" id="modalTitle">Thêm Sản Phẩm Mới</h3>
                    <button type="button" onclick="closeModal('productModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
                </div>

                <div class="px-6 py-6 overflow-y-auto custom-scrollbar flex-1 bg-slate-50/30">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Tên sản phẩm <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="product_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Danh mục <span class="text-red-500">*</span></label>
                            <select name="category_id" id="product_category" class="select2-search w-full" required>
                                <?php foreach ($categoriesList as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Tác giả</label>
                            <input type="text" name="author" id="product_author" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Nhà xuất bản</label>
                            <input type="text" name="publisher" id="product_publisher" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Năm xuất bản</label>
                            <input type="number" name="publication_year" id="product_publication_year" min="0" max="9999" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Giá gốc <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="text" name="price" id="product_price" required class="price-input w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-4 pr-12 text-slate-800 font-bold focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">VNĐ</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Giá ưu đãi</label>
                            <div class="relative">
                                <input type="text" name="discount_price" id="product_discount" class="price-input w-full bg-slate-50 border border-slate-200 rounded-xl py-3 pl-4 pr-12 text-slate-800 font-bold focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">VNĐ</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Số lượng <span class="text-red-500">*</span></label>
                            <input type="number" name="quantity" id="product_quantity" required min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Hình ảnh</label>
                            <input type="file" name="image" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-brown-50 file:text-brown-700 hover:file:bg-brown-100 transition-all cursor-pointer bg-slate-50 border border-slate-200 rounded-xl">
                            <div id="imagePreview" class="mt-2 hidden">
                                <img src="" id="img_preview_tag" class="h-20 rounded-lg border border-slate-200 object-cover">
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Mô tả sản phẩm</label>
                            <textarea name="description" id="product_desc" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all custom-scrollbar"></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-white px-6 py-4 flex justify-end gap-3 rounded-b-2xl border-t border-slate-100 shrink-0">
                    <button type="button" onclick="closeModal('productModal')" class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-50 transition-colors">Hủy</button>
                    <button type="submit" class="bg-brown-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-brown-700 transition-colors shadow-md shadow-brown-500/30">Lưu Sản Phẩm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>.swal2-popup.custom-swal { border-radius: 1.5rem !important; }</style>

<script>
    $(document).ready(function() {
        $('.select2-search').select2({
            placeholder: "Chọn danh mục",
            width: '100%',
            dropdownParent: $('#productModal')
        });

        $('.price-input').on('input', function(e) {
            let value = e.target.value;
            value = value.replace(/\D/g, "");
            if(value !== "") {
                value = parseInt(value, 10).toLocaleString('en-US');
            }
            e.target.value = value;
        });
    });

    function formatNumberStr(num) {
        if(!num) return "";
        return parseInt(num, 10).toLocaleString('en-US');
    }

    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.style.overflow = '';
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Thêm Sản Phẩm Mới';
        document.getElementById('form_action').value = 'add';
        document.getElementById('product_id').value = '';
        document.getElementById('product_name').value = '';
        document.getElementById('product_price').value = '';
        document.getElementById('product_discount').value = '';
        document.getElementById('product_quantity').value = '';
        document.getElementById('product_desc').value = '';
        document.getElementById('product_author').value = '';
        document.getElementById('product_publisher').value = '';
        document.getElementById('product_publication_year').value = '';
        document.getElementById('old_image').value = '';
        document.getElementById('imagePreview').classList.add('hidden');
        $('#product_category').val(null).trigger('change');
        openModal('productModal');
    }

    function openEditModal(product) {
        document.getElementById('modalTitle').innerText = 'Cập Nhật Sản Phẩm';
        document.getElementById('form_action').value = 'edit';
        document.getElementById('product_id').value = product.id;
        document.getElementById('product_name').value = product.name;
        document.getElementById('product_price').value = formatNumberStr(product.price);
        document.getElementById('product_discount').value = formatNumberStr(product.discount_price);
        document.getElementById('product_quantity').value = product.quantity;
        document.getElementById('product_desc').value = product.description;
        document.getElementById('product_author').value = product.author || '';
        document.getElementById('product_publisher').value = product.publisher || '';
        document.getElementById('product_publication_year').value = product.publication_year || '';
        document.getElementById('old_image').value = product.image;
        
        $('#product_category').val(product.category_id).trigger('change');

        if(product.image) {
            document.getElementById('imagePreview').classList.remove('hidden');
            document.getElementById('img_preview_tag').src = '<?= $uploadDir ?>' + product.image;
        } else {
            document.getElementById('imagePreview').classList.add('hidden');
        }

        openModal('productModal');
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Xóa sản phẩm này?',
            text: "Dữ liệu sẽ không thể khôi phục!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e3342f',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Đồng ý xóa',
            cancelButtonText: 'Hủy thao tác',
            customClass: { popup: 'custom-swal' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm_' + id).submit();
            }
        });
    }

    <?php if ($statusMsg !== ''): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: { popup: 'rounded-2xl shadow-2xl border border-slate-100' }
        });
        Toast.fire({
            icon: '<?= $statusType ?>',
            title: '<?= $statusMsg ?>'
        });
    });
    <?php endif; ?>
</script>

<?php require_once 'footer.php'; ?>