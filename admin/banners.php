<?php
require_once 'header.php';

$statusMsg = '';
$statusType = '';

$uploadDir = '../uploads/banners/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $title = trim($_POST['title']);
            $link = trim($_POST['link']);
            $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
            $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            
            $imageName = $_POST['old_image'] ?? '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $imageName = 'banner_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
                }
            }

            if ($action === 'add') {
                if (empty($imageName)) {
                    $statusMsg = 'Vui lòng chọn hình ảnh banner!';
                    $statusType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO banners (title, link, image, sort_order, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $link, $imageName, $sort_order, $status]);
                    $statusMsg = 'Thêm banner thành công!';
                    $statusType = 'success';
                }
            } elseif ($action === 'edit') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE banners SET title=?, link=?, image=?, sort_order=?, status=? WHERE id=?");
                $stmt->execute([$title, $link, $imageName, $sort_order, $status, $id]);
                $statusMsg = 'Cập nhật banner thành công!';
                $statusType = 'success';
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            if ($id > 0) {
                $stmtImg = $pdo->prepare("SELECT image FROM banners WHERE id = ?");
                $stmtImg->execute([$id]);
                $img = $stmtImg->fetchColumn();
                if ($img && file_exists($uploadDir . $img)) {
                    unlink($uploadDir . $img);
                }

                $stmt = $pdo->prepare("DELETE FROM banners WHERE id = ?");
                $stmt->execute([$id]);
                $statusMsg = 'Đã xóa banner!';
                $statusType = 'success';
            }
        }
    } catch (\PDOException $e) {
        $statusMsg = 'Có lỗi xảy ra với cơ sở dữ liệu!';
        $statusType = 'error';
    }
}

$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = per_page(10);
$offset = ($page - 1) * $limit;

$whereClause = '';
$params = [];
if ($search !== '') {
    $whereClause = "WHERE title LIKE ?";
    $params[] = "%$search%";
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM banners $whereClause");
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT * FROM banners $whereClause ORDER BY sort_order ASC, id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$banners = $stmt->fetchAll();
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-4 sm:p-6 border-b border-slate-100 bg-slate-50/50">
        <div class="flex flex-row justify-between items-center gap-3">
            <form method="GET" action="" class="flex-1 max-w-md relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm banner..." class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-11 pr-4 text-slate-700 text-sm focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
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
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-l-lg uppercase tracking-wider w-16">Thứ Tự</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider w-48">Hình Ảnh</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Tiêu Đề</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Liên Kết</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider w-32">Trạng Thái</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-r-lg text-right uppercase tracking-wider w-40">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($banners)): ?>
                    <tr><td colspan="6" class="py-12 text-center text-slate-400 font-medium">Không tìm thấy banner nào</td></tr>
                <?php else: ?>
                    <?php foreach ($banners as $b): ?>
                        <tr class="hover:bg-slate-50 transition-colors group border-b border-slate-50">
                            <td data-label="Thứ Tự" class="py-4 px-4 font-bold text-slate-800"><?= $b['sort_order'] ?></td>
                            <td data-label="Hình Ảnh" class="py-4 px-4">
                                <?php if ($b['image']): ?>
                                    <img src="<?= $uploadDir . $b['image'] ?>" alt="Banner" class="h-16 w-32 object-cover rounded-lg border border-slate-200">
                                <?php else: ?>
                                    <div class="h-16 w-32 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 border border-slate-200"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Tiêu Đề" class="py-4 px-4 font-bold text-brown-700"><?= htmlspecialchars($b['title'] ?? '---') ?></td>
                            <td data-label="Liên Kết" class="py-4 px-4 font-medium text-slate-500 truncate max-w-[150px]"><?= htmlspecialchars($b['link'] ?? '---') ?></td>
                            <td data-label="Trạng Thái" class="py-4 px-4">
                                <?php if ($b['status'] == 1): ?>
                                    <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wide">Hiển thị</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold uppercase tracking-wide">Đã ẩn</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Thao Tác" class="py-4 px-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='openEditModal(<?= json_encode($b) ?>)' class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm">
                                        <i class="fas fa-edit"></i><span class="hidden sm:inline sm:ml-1">Sửa</span>
                                    </button>
                                    <button onclick="confirmDelete(<?= $b['id'] ?>)" class="bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm">
                                        <i class="fas fa-trash-alt"></i><span class="hidden sm:inline sm:ml-1">Xóa</span>
                                    </button>
                                    <form id="deleteForm_<?= $b['id'] ?>" method="POST" action="" class="hidden">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
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

<div id="bannerModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('bannerModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg w-full border border-slate-100 flex flex-col">
            <form method="POST" action="" enctype="multipart/form-data" class="flex flex-col h-full">
                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="id" id="banner_id">
                <input type="hidden" name="old_image" id="old_image">

                <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white shrink-0">
                    <h3 class="text-xl font-black text-slate-800" id="modalTitle">Thêm Banner Mới</h3>
                    <button type="button" onclick="closeModal('bannerModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
                </div>

                <div class="px-6 py-6 overflow-y-auto custom-scrollbar flex-1 bg-slate-50/30 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Tiêu đề (Tùy chọn)</label>
                        <input type="text" name="title" id="banner_title" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Liên kết (Link URL)</label>
                        <input type="url" name="link" id="banner_link" placeholder="https://" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Thứ tự hiển thị</label>
                            <input type="number" name="sort_order" id="banner_sort" value="0" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Trạng thái</label>
                            <select name="status" id="banner_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                                <option value="1">Hiển thị</option>
                                <option value="0">Ẩn</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Hình ảnh banner <span class="text-red-500">*</span></label>
                        <input type="file" name="image" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-brown-50 file:text-brown-700 hover:file:bg-brown-100 transition-all cursor-pointer bg-slate-50 border border-slate-200 rounded-xl">
                        <div id="imagePreview" class="mt-3 hidden">
                            <img src="" id="img_preview_tag" class="w-full h-32 rounded-lg border border-slate-200 object-cover">
                        </div>
                    </div>
                </div>

                <div class="bg-white px-6 py-4 flex justify-end gap-3 rounded-b-2xl border-t border-slate-100 shrink-0">
                    <button type="button" onclick="closeModal('bannerModal')" class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-50 transition-colors">Hủy</button>
                    <button type="submit" class="bg-brown-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-brown-700 transition-colors shadow-md shadow-brown-500/30">Lưu Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>.swal2-popup.custom-swal { border-radius: 1.5rem !important; }</style>

<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.style.overflow = '';
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Thêm Banner Mới';
        document.getElementById('form_action').value = 'add';
        document.getElementById('banner_id').value = '';
        document.getElementById('banner_title').value = '';
        document.getElementById('banner_link').value = '';
        document.getElementById('banner_sort').value = '0';
        document.getElementById('banner_status').value = '1';
        document.getElementById('old_image').value = '';
        document.getElementById('imagePreview').classList.add('hidden');
        openModal('bannerModal');
    }

    function openEditModal(banner) {
        document.getElementById('modalTitle').innerText = 'Cập Nhật Banner';
        document.getElementById('form_action').value = 'edit';
        document.getElementById('banner_id').value = banner.id;
        document.getElementById('banner_title').value = banner.title || '';
        document.getElementById('banner_link').value = banner.link || '';
        document.getElementById('banner_sort').value = banner.sort_order;
        document.getElementById('banner_status').value = banner.status;
        document.getElementById('old_image').value = banner.image;
        
        if(banner.image) {
            document.getElementById('imagePreview').classList.remove('hidden');
            document.getElementById('img_preview_tag').src = '<?= $uploadDir ?>' + banner.image;
        } else {
            document.getElementById('imagePreview').classList.add('hidden');
        }

        openModal('bannerModal');
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Xóa banner này?',
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