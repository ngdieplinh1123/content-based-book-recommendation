<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['action']) && $_GET['action'] === 'ckupload') {
    header('Content-Type: application/json');
    
    $uploadDir = '../uploads/news_content/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $imageName = 'content_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['upload']['tmp_name'], $uploadDir . $imageName)) {
                
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $baseDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
                $baseDir = str_replace('\\', '/', $baseDir);
                if ($baseDir === '/') $baseDir = '';
                
                $url = $protocol . "://" . $host . $baseDir . '/uploads/news_content/' . $imageName;

                echo json_encode([
                    "uploaded" => 1,
                    "fileName" => $imageName,
                    "url" => $url
                ]);
                exit;
            }
        }
    }
    echo json_encode([
        "uploaded" => 0,
        "error" => ["message" => "Lỗi tải ảnh lên"]
    ]);
    exit;
}

require_once 'header.php';

$statusMsg = '';
$statusType = '';

$uploadDir = '../uploads/news/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            
            $imageName = $_POST['old_image'] ?? '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed)) {
                    $imageName = 'news_' . time() . '_' . uniqid() . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
                }
            }

            if ($action === 'add') {
                if (empty($title)) {
                    $statusMsg = 'Tiêu đề không được để trống!';
                    $statusType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO news (title, content, image) VALUES (?, ?, ?)");
                    $stmt->execute([$title, $content, $imageName]);
                    $statusMsg = 'Thêm tin tức thành công!';
                    $statusType = 'success';
                }
            } elseif ($action === 'edit') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE news SET title=?, content=?, image=? WHERE id=?");
                $stmt->execute([$title, $content, $imageName, $id]);
                $statusMsg = 'Cập nhật tin tức thành công!';
                $statusType = 'success';
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            if ($id > 0) {
                $stmtImg = $pdo->prepare("SELECT image FROM news WHERE id = ?");
                $stmtImg->execute([$id]);
                $img = $stmtImg->fetchColumn();
                if ($img && file_exists($uploadDir . $img)) {
                    unlink($uploadDir . $img);
                }

                $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
                $stmt->execute([$id]);
                $statusMsg = 'Đã xóa tin tức!';
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

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM news $whereClause");
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT * FROM news $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$newsList = $stmt->fetchAll();
?>

<script src="https://cdn.ckeditor.com/4.22.1/full-all/ckeditor.js"></script>

<style>
    .cke_notifications_area { display: none !important; }
    .cke_dialog_background_cover { z-index: 999998 !important; }
    .cke_dialog, .cke_maximized, .cke_float { z-index: 999999 !important; }
    
    .cke_reset_all, .cke_reset_all * { 
        box-sizing: content-box !important; 
    }
    .cke_dialog_ui_input_text input, 
    .cke_dialog_ui_input_password input, 
    .cke_dialog_ui_input_textarea textarea { 
        box-sizing: border-box !important; 
    }

    #newsModal { transition: opacity 0.3s ease; }
    #newsModalContent { transition: transform 0.3s ease; transform: translateY(-20px); }
    #newsModal.active #newsModalContent { transform: translateY(0); }
    
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-4 sm:p-6 border-b border-slate-100 bg-slate-50/50">
        <div class="flex flex-row justify-between items-center gap-3">
            <form method="GET" action="" class="flex-1 max-w-md relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm kiếm tin tức..." class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-11 pr-4 text-slate-700 text-sm focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
            </form>
            <button type="button" onclick="openAddModal()" class="bg-brown-600 hover:bg-brown-700 text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-colors shadow-md shadow-brown-500/30 flex items-center shrink-0">
                <i class="fas fa-plus"></i><span class="hidden sm:inline sm:ml-2">Thêm Mới</span>
            </button>
        </div>
    </div>

    <div class="p-4 sm:p-6 overflow-x-auto">
        <?= per_page_selector($limit, $page, (int)$totalRecords) ?>
        <table class="w-full text-left border-collapse min-w-[800px]">
            <thead>
                <tr>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-l-lg uppercase tracking-wider w-16">ID</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider w-32">Ảnh</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider">Tiêu Đề</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm uppercase tracking-wider w-40">Ngày Đăng</th>
                    <th class="py-3 px-4 bg-slate-50 font-bold text-slate-500 text-sm rounded-r-lg text-right uppercase tracking-wider w-40">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($newsList)): ?>
                    <tr><td colspan="5" class="py-12 text-center text-slate-400 font-medium">Không tìm thấy bản tin nào</td></tr>
                <?php else: ?>
                    <?php foreach ($newsList as $news): ?>
                        <tr class="hover:bg-slate-50 transition-colors group border-b border-slate-50">
                            <td class="py-4 px-4 font-bold text-slate-800"><?= $news['id'] ?></td>
                            <td class="py-4 px-4">
                                <?php if ($news['image']): ?>
                                    <img src="<?= $uploadDir . $news['image'] ?>" alt="News" class="h-16 w-24 object-cover rounded-lg border border-slate-200">
                                <?php else: ?>
                                    <div class="h-16 w-24 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 border border-slate-200"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 font-bold text-brown-700 max-w-xs truncate"><?= htmlspecialchars($news['title']) ?></td>
                            <td class="py-4 px-4 font-medium text-slate-600"><?= date('d/m/Y H:i', strtotime($news['created_at'])) ?></td>
                            <td class="py-4 px-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm" onclick="openEditModal(this)" data-id="<?= $news['id'] ?>" data-title="<?= htmlspecialchars($news['title'], ENT_QUOTES, 'UTF-8') ?>" data-image="<?= $news['image'] ?>">
                                        <textarea class="raw-content" style="display:none;"><?= htmlspecialchars($news['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                        <i class="fas fa-edit"></i><span class="hidden sm:inline sm:ml-1">Sửa</span>
                                    </button>
                                    <button type="button" onclick="confirmDelete(<?= $news['id'] ?>)" class="bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded-lg font-bold text-sm transition-colors flex items-center shadow-sm">
                                        <i class="fas fa-trash-alt"></i><span class="hidden sm:inline sm:ml-1">Xóa</span>
                                    </button>
                                    <form id="deleteForm_<?= $news['id'] ?>" method="POST" action="" class="hidden">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $news['id'] ?>">
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

<div id="newsModal" class="fixed inset-0 z-[100] opacity-0 pointer-events-none flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal()"></div>
    
    <div id="newsModalContent" class="relative bg-white rounded-2xl w-full max-w-4xl shadow-2xl flex flex-col" style="max-height: calc(100vh - 40px);">
        <form method="POST" action="" enctype="multipart/form-data" class="flex flex-col h-full overflow-hidden rounded-2xl" onsubmit="syncEditor()">
            <input type="hidden" name="action" id="inputAction" value="add">
            <input type="hidden" name="id" id="inputId">
            <input type="hidden" name="old_image" id="oldImage">

            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-white shrink-0">
                <h3 class="text-xl font-black text-slate-800" id="modalTitle">Thêm Tin Tức Mới</h3>
                <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-red-500 transition-colors p-2 rounded-full hover:bg-red-50">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/50 custom-scrollbar">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Tiêu Đề <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="inputTitle" required class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-slate-800 font-medium focus:outline-none focus:border-brown-500 focus:ring-1 focus:ring-brown-500 transition-all shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Ảnh Đại Diện</label>
                    <input type="file" name="image" accept="image/*" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-brown-50 file:text-brown-700 hover:file:bg-brown-100 transition-all cursor-pointer shadow-sm">
                    <div id="imagePreview" class="mt-3 hidden">
                        <img src="" id="imgPreviewTag" class="h-24 w-32 rounded-lg border border-slate-200 object-cover shadow-sm">
                    </div>
                </div>

                <div class="relative z-0">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Nội Dung Chi Tiết <span class="text-red-500">*</span></label>
                    <textarea name="content" id="content_editor" required></textarea>
                </div>
            </div>

            <div class="px-6 py-4 bg-white border-t border-slate-100 flex justify-end gap-3 shrink-0">
                <button type="button" onclick="closeModal()" class="bg-white border border-slate-200 text-slate-700 px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-50 transition-colors">Hủy Bỏ</button>
                <button type="submit" class="bg-brown-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-brown-700 transition-colors shadow-md shadow-brown-500/30">Lưu Thông Tin</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        CKEDITOR.replace('content_editor', {
            filebrowserUploadUrl: '?action=ckupload',
            filebrowserUploadMethod: 'xhr',
            height: 300,
            baseFloatZIndex: 999999,
            extraPlugins: 'image2,uploadimage',
            removePlugins: 'image',
            image2_alignClasses: [ 'image-align-left', 'image-align-center', 'image-align-right' ],
            image2_disableResizer: false,
            removeDialogTabs: 'image:advanced;link:advanced'
        });
    });

    function syncEditor() {
        for (instance in CKEDITOR.instances) {
            CKEDITOR.instances[instance].updateElement();
        }
    }

    function openModal() {
        const modal = document.getElementById('newsModal');
        modal.classList.remove('opacity-0', 'pointer-events-none');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('newsModal');
        modal.classList.add('opacity-0', 'pointer-events-none');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Thêm Tin Tức Mới';
        document.getElementById('inputAction').value = 'add';
        document.getElementById('inputId').value = '';
        document.getElementById('inputTitle').value = '';
        document.getElementById('oldImage').value = '';
        document.getElementById('imagePreview').classList.add('hidden');
        
        if (CKEDITOR.instances['content_editor']) {
            CKEDITOR.instances['content_editor'].setData('');
        }
        
        openModal();
    }

    function openEditModal(button) {
        document.getElementById('modalTitle').innerText = 'Cập Nhật Tin Tức';
        document.getElementById('inputAction').value = 'edit';
        
        const id = button.getAttribute('data-id');
        const title = button.getAttribute('data-title');
        const image = button.getAttribute('data-image');
        const content = button.querySelector('.raw-content').value;

        document.getElementById('inputId').value = id;
        document.getElementById('inputTitle').value = title;
        document.getElementById('oldImage').value = image;

        if (image) {
            document.getElementById('imagePreview').classList.remove('hidden');
            document.getElementById('imgPreviewTag').src = '<?= $uploadDir ?>' + image;
        } else {
            document.getElementById('imagePreview').classList.add('hidden');
        }

        if (CKEDITOR.instances['content_editor']) {
            CKEDITOR.instances['content_editor'].setData(content);
        }
        
        openModal();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Xóa bài viết này?',
            text: "Dữ liệu không thể khôi phục sau khi xóa!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e3342f',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Đồng ý xóa',
            cancelButtonText: 'Hủy thao tác',
            customClass: { popup: 'rounded-2xl shadow-xl border border-slate-100' }
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
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: { popup: 'rounded-2xl shadow-xl border border-slate-100' }
        });
        Toast.fire({
            icon: '<?= $statusType ?>',
            title: '<?= $statusMsg ?>'
        });
    });
    <?php endif; ?>
</script>

<?php require_once 'footer.php'; ?>