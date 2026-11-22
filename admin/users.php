<?php
require_once 'header.php';

$statusMsg = '';
$statusType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'add') {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $role = trim($_POST['role']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            
            if (!empty($username) && !empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, phone, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $role, $phone, $address]);
                $statusMsg = 'Thêm người dùng thành công!';
                $statusType = 'success';
            } else {
                $statusMsg = 'Tên đăng nhập và mật khẩu không được để trống!';
                $statusType = 'error';
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $role = trim($_POST['role']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            
            if (!empty($username) && $id > 0) {
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([$username, $hashedPassword, $role, $phone, $address, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $phone, $address, $id]);
                }
                $statusMsg = 'Cập nhật người dùng thành công!';
                $statusType = 'success';
            } else {
                $statusMsg = 'Dữ liệu không hợp lệ!';
                $statusType = 'error';
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $statusMsg = 'Đã xóa người dùng!';
                $statusType = 'success';
            }
        }
    } catch (\PDOException $e) {
        $statusMsg = 'Tên đăng nhập đã tồn tại hoặc có lỗi cơ sở dữ liệu!';
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
    $whereClause = "WHERE username LIKE ? OR phone LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM users $whereClause");
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT id, username, role, phone, address, created_at FROM users $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-4 sm:p-6 border-b border-slate-100 bg-slate-50/50">
        <div class="flex flex-row justify-between items-center gap-3">
            <form method="GET" action="" class="flex-1 max-w-md relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm tài khoản, số điện thoại..." class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-11 pr-4 text-slate-700 text-sm focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
            </form>
            <button onclick="openModal('addModal')" class="bg-brown-600 hover:bg-brown-700 text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-colors shadow-md shadow-brown-500/30 flex items-center shrink-0">
                <i class="fas fa-plus"></i><span class="hidden sm:inline sm:ml-2">Thêm Mới</span>
            </button>
        </div>
    </div>

    <div class="p-4 sm:p-6 overflow-x-auto">
        <?= per_page_selector($limit, $page, (int)$totalRecords) ?>
        <table class="w-full text-left border-collapse md:min-w-[800px] mobile-card-table">
            <thead>
                <tr>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-l-lg uppercase tracking-wider w-16">ID</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Tài Khoản</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Vai Trò</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Số Điện Thoại</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Địa Chỉ</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-r-lg text-right uppercase tracking-wider w-40">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="py-12 text-center text-slate-400 font-medium">Không tìm thấy dữ liệu nào</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td data-label="ID" class="py-4 px-4 font-bold text-slate-800 border-b border-slate-50"><?= $user['id'] ?></td>
                            <td data-label="Tài Khoản" class="py-4 px-4 font-bold text-brown-700 border-b border-slate-50"><?= htmlspecialchars($user['username']) ?></td>
                            <td data-label="Vai Trò" class="py-4 px-4 border-b border-slate-50">
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-bold uppercase tracking-wide">Quản trị</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-bold uppercase tracking-wide">Khách hàng</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Số Điện Thoại" class="py-4 px-4 border-b border-slate-50 font-medium text-slate-600"><?= htmlspecialchars($user['phone']) ?></td>
                            <td data-label="Địa Chỉ" class="py-4 px-4 border-b border-slate-50 text-slate-600"><?= htmlspecialchars($user['address']) ?></td>
                            <td data-label="Thao Tác" class="py-4 px-4 border-b border-slate-50 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick="openEditModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['username'])) ?>', '<?= htmlspecialchars(addslashes($user['role'])) ?>', '<?= htmlspecialchars(addslashes($user['phone'])) ?>', '<?= htmlspecialchars(addslashes($user['address'])) ?>')" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm">
                                        <i class="fas fa-edit"></i><span class="hidden sm:inline sm:ml-1">Sửa</span>
                                    </button>
                                    <button onclick="confirmDelete(<?= $user['id'] ?>)" class="bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm">
                                        <i class="fas fa-trash-alt"></i><span class="hidden sm:inline sm:ml-1">Xóa</span>
                                    </button>
                                    <form id="deleteForm_<?= $user['id'] ?>" method="POST" action="" class="hidden">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
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
                        <h3 class="text-xl font-black text-slate-800">Thêm Người Dùng</h3>
                        <button type="button" onclick="closeModal('addModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Tên đăng nhập <span class="text-red-500">*</span></label>
                            <input type="text" name="username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Mật khẩu <span class="text-red-500">*</span></label>
                            <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Vai trò</label>
                            <select name="role" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                                <option value="user">Khách hàng</option>
                                <option value="admin">Quản trị viên</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Số điện thoại</label>
                            <input type="text" name="phone" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Địa chỉ</label>
                            <input type="text" name="address" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
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
                        <h3 class="text-xl font-black text-slate-800">Cập Nhật Người Dùng</h3>
                        <button type="button" onclick="closeModal('editModal')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Tên đăng nhập</label>
                            <input type="text" name="username" id="edit_username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Mật khẩu mới</label>
                            <input type="password" name="password" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all" placeholder="Bỏ trống nếu không đổi mật khẩu">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Vai trò</label>
                            <select name="role" id="edit_role" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                                <option value="user">Khách hàng</option>
                                <option value="admin">Quản trị viên</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Số điện thoại</label>
                            <input type="text" name="phone" id="edit_phone" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Địa chỉ</label>
                            <input type="text" name="address" id="edit_address" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all">
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

    function openEditModal(id, username, role, phone, address) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_role').value = role;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_address').value = address;
        openModal('editModal');
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Xóa tài khoản này?',
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

<?php require_once 'footer.php'; ?>xa