<?php
require_once 'header.php';

$statusMsg = '';
$statusType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'add') {
            $name = trim($_POST['name']);
            $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
            
            if (!empty($name)) {
                $stmt = $pdo->prepare("INSERT INTO categories (name, status) VALUES (?, ?)");
                $stmt->execute([$name, $status]);
                $statusMsg = 'Thêm danh mục thành công!';
                $statusType = 'success';
            } else {
                $statusMsg = 'Tên danh mục không được để trống!';
                $statusType = 'error';
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
            
            if (!empty($name) && $id > 0) {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $status, $id]);
                $statusMsg = 'Cập nhật danh mục thành công!';
                $statusType = 'success';
            } else {
                $statusMsg = 'Dữ liệu không hợp lệ!';
                $statusType = 'error';
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $statusMsg = 'Đã xóa danh mục!';
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
    $whereClause = "WHERE name LIKE ?";
    $params[] = "%$search%";
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM categories $whereClause");
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT * FROM categories $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-4 sm:p-6 border-b border-slate-100 bg-slate-50/50">
        <div class="flex flex-row justify-between items-center gap-3">
            <form method="GET" action="" class="flex-1 max-w-md relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm danh mục..." class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-11 pr-4 text-slate-700 text-sm focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
            </form>
            <button onclick="openModal('addModal')" class="bg-brown-600 hover:bg-brown-700 text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-colors shadow-md shadow-brown-500/30 flex items-center shrink-0">
                <i class="fas fa-plus"></i><span class="hidden sm:inline sm:ml-2">Thêm Mới</span>
            </button>
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <?= per_page_selector($limit, $page, (int)$totalRecords) ?>
        <table class="w-full text-left border-collapse mobile-card-table">
            <thead>
                <tr>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-l-lg uppercase tracking-wider w-20">Mã</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Tên Danh Mục</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider w-40">Trạng Thái</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-r-lg text-right uppercase tracking-wider w-48">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="4" class="py-12 text-center text-slate-400 font-medium">Không tìm thấy dữ liệu nào</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td data-label="Mã" class="py-4 px-4 font-bold text-slate-800 border-b border-slate-50"><?= $cat['id'] ?></td>
                            <td data-label="Tên Danh Mục" class="py-4 px-4 font-bold text-brown-700 border-b border-slate-50"><?= htmlspecialchars($cat['name']) ?></td>
                            <td data-label="Trạng Thái" class="py-4 px-4 border-b border-slate-50">
                                <?php if ($cat['status'] == 1): ?>
                                    <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold uppercase tracking-wide">Hiển thị</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold uppercase tracking-wide">Đã ẩn</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Thao Tác" class="py-4 px-4 border-b border-slate-50 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>', <?= $cat['status'] ?>)" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm">
                                        <i class="fas fa-edit"></i><span class="hidden sm:inline sm:ml-1">Sửa</span>
                                    </button>
                                    <button onclick="confirmDelete(<?= $cat['id'] ?>)" class="bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm">
                                        <i class="fas fa-trash-alt"></i><span class="hidden sm:inline sm:ml-1">Xóa</span>
                                    </button>
                                    <form id="deleteForm_<?= $cat['id'] ?>" method="POST" action="" class="hidden">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
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

<div id="addModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('addModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg w-full border border-slate-100">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                <div class="bg-white px-6 py-6">
                    <div class="flex justify-between items-center mb-5">
                        <h3 class="text-xl font-black text-slate-800">Thêm Danh Mục Mới</h3>
                        <button type="button" onclick="closeModal('addModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Tên danh mục</label>
                            <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Trạng thái</label>
                            <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                                <option value="1">Hiển thị</option>
                                <option value="0">Ẩn</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex justify-end gap-3 rounded-b-2xl">
                    <button type="button" onclick="closeModal('addModal')" class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-100 transition-colors">Hủy</button>
                    <button type="submit" class="bg-brown-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-brown-700 transition-colors shadow-md shadow-brown-500/30">Lưu Dữ Liệu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal('editModal')"></div>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg w-full border border-slate-100">
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="bg-white px-6 py-6">
                    <div class="flex justify-between items-center mb-5">
                        <h3 class="text-xl font-black text-slate-800">Cập Nhật Danh Mục</h3>
                        <button type="button" onclick="closeModal('editModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Tên danh mục</label>
                            <input type="text" name="name" id="edit_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Trạng thái</label>
                            <select name="status" id="edit_status" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                                <option value="1">Hiển thị</option>
                                <option value="0">Ẩn</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex justify-end gap-3 rounded-b-2xl">
                    <button type="button" onclick="closeModal('editModal')" class="bg-white border border-slate-200 text-slate-700 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-100 transition-colors">Hủy</button>
                    <button type="submit" class="bg-brown-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-brown-700 transition-colors shadow-md shadow-brown-500/30">Cập Nhật</button>
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

    function openEditModal(id, name, status) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_status').value = status;
        openModal('editModal');
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Xóa danh mục này?',
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