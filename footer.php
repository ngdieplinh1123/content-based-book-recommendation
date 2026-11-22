</main>

    <footer class="bg-brown-900 pt-16 pb-8 mt-auto border-t-[6px] border-brown-500">
        <div class="container mx-auto px-4 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
                
                <div>
                    <a href="index.php" class="flex items-center gap-2 text-3xl font-black text-white tracking-wide mb-6">
                        <img src="<?= $siteLogo ?>" alt="" class="h-10 w-10 object-contain bg-white">
                        <span><?= $siteName ?></span>
                    </a>
                    <p class="text-brown-200 text-sm leading-relaxed mb-6">Một hiệu sách nhỏ gọn, chọn lọc những đầu sách dễ đọc, dễ mua và hợp gu mỗi ngày.</p>
                    <div class="flex gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-brown-800 flex items-center justify-center text-white hover:bg-brown-500 hover:-translate-y-1 transition-all"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-brown-800 flex items-center justify-center text-white hover:bg-brown-500 hover:-translate-y-1 transition-all"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div>
                    <h4 class="text-white font-bold text-lg mb-6 uppercase tracking-widest relative inline-block">
                        Liên Kết Mua Sắm
                        <span class="absolute -bottom-2 left-0 w-1/2 h-1 bg-brown-500 rounded-full"></span>
                    </h4>
                    <ul class="space-y-3">
                        <li><a href="index.php" class="text-brown-200 hover:text-white hover:pl-2 transition-all text-sm flex items-center"><i class="fas fa-chevron-right text-[10px] mr-2 text-brown-500"></i>Trang chủ</a></li>
                        <li><a href="products.php" class="text-brown-200 hover:text-white hover:pl-2 transition-all text-sm flex items-center"><i class="fas fa-chevron-right text-[10px] mr-2 text-brown-500"></i>Tất cả sản phẩm</a></li>
                        <li><a href="news.php" class="text-brown-200 hover:text-white hover:pl-2 transition-all text-sm flex items-center"><i class="fas fa-chevron-right text-[10px] mr-2 text-brown-500"></i>Tin tức mới nhất</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-bold text-lg mb-6 uppercase tracking-widest relative inline-block">
                        Chính Sách
                        <span class="absolute -bottom-2 left-0 w-1/2 h-1 bg-brown-500 rounded-full"></span>
                    </h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-brown-200 hover:text-white hover:pl-2 transition-all text-sm flex items-center"><i class="fas fa-chevron-right text-[10px] mr-2 text-brown-500"></i>Bảo mật thông tin</a></li>
                        <li><a href="#" class="text-brown-200 hover:text-white hover:pl-2 transition-all text-sm flex items-center"><i class="fas fa-chevron-right text-[10px] mr-2 text-brown-500"></i>Đổi trả hàng hóa</a></li>
                        <li><a href="#" class="text-brown-200 hover:text-white hover:pl-2 transition-all text-sm flex items-center"><i class="fas fa-chevron-right text-[10px] mr-2 text-brown-500"></i>Vận chuyển & Giao nhận</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-bold text-lg mb-6 uppercase tracking-widest relative inline-block">
                        Nhận Tin Sách Hay
                        <span class="absolute -bottom-2 left-0 w-1/2 h-1 bg-brown-500 rounded-full"></span>
                    </h4>
                    <form class="flex flex-col gap-3">
                        <input type="email" placeholder="Email của bạn..." class="w-full bg-brown-800 border border-brown-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-brown-400 transition-colors text-sm">
                        <button type="button" class="w-full bg-brown-500 hover:bg-brown-400 text-white font-bold py-3 rounded-xl transition-colors text-sm uppercase tracking-wider shadow-lg shadow-brown-900/50">Theo dõi</button>
                    </form>
                </div>
            </div>

            <div class="border-t border-brown-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-brown-400 text-sm text-center md:text-left">
                    &copy; 2026 Bản quyền thuộc về <?= $siteName ?>. Hỗ trợ: <?= $sitePhone ?> - <?= $siteEmail ?>
                </p>
                <div class="text-brown-400 text-sm font-medium">
                    Hoàn thiện giao diện bởi <a href="#" class="text-white hover:text-brown-300 font-bold transition-colors underline decoration-brown-500 underline-offset-4 tracking-wide"><?= $siteName ?></a>
                </div>
            </div>
        </div>
    </footer>

    <?php include __DIR__ . '/chatbot_widget.php'; ?>

</body>
</html>