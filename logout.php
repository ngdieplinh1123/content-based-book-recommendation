<?php
require_once 'header.php';

session_unset();
session_destroy();
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container mx-auto px-4 py-32 md:py-48 flex flex-col items-center justify-center min-h-[50vh]">
    <div class="w-20 h-20 bg-brown-50 rounded-full flex items-center justify-center mb-6 shadow-inner border border-brown-100">
        <i class="fas fa-spinner fa-spin text-4xl text-brown-500"></i>
    </div>
    <h2 class="text-2xl font-black text-brown-800">Đang lưu dữ liệu...</h2>
    <p class="text-brown-500 mt-2 font-medium">Hệ thống đang đăng xuất tài khoản của bạn an toàn.</p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
            customClass: { popup: 'rounded-2xl shadow-2xl border border-brown-100' }
        });

        Toast.fire({
            icon: 'success',
            title: 'Đã đăng xuất thành công!',
            iconColor: '#ca7344'
        }).then(() => {
            window.location.href = 'login.php';
        });
    });
</script>

<?php
require_once 'footer.php';
?>