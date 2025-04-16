<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse" style="height: 1400px;">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <a href="index.php" class="text-decoration-none">
                <img src="../images/logo.png" alt="Yemeksepeti" width="120" class="img-fluid mb-2">
                <div class="text-white">Admin Paneli</div>
            </a>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'dashboard.php' ? 'active bg-primary' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Kontrol Paneli
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'orders.php' || basename($_SERVER['SCRIPT_NAME']) == 'order_detail.php' ? 'active bg-primary' : ''; ?>" href="orders.php">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Siparişler
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'payment_logs.php' ? 'active bg-primary' : ''; ?>" href="payment_logs.php">
                    <i class="fas fa-credit-card me-2"></i>
                    Ödeme İşlemleri
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'products.php' || basename($_SERVER['SCRIPT_NAME']) == 'product_edit.php' || basename($_SERVER['SCRIPT_NAME']) == 'product_add.php' ? 'active bg-primary' : ''; ?>" href="products.php">
                    <i class="fas fa-box me-2"></i>
                    Ürünler
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'categories.php' || basename($_SERVER['SCRIPT_NAME']) == 'category_edit.php' ? 'active bg-primary' : ''; ?>" href="categories.php">
                    <i class="fas fa-tags me-2"></i>
                    Kategoriler
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'users.php' || basename($_SERVER['SCRIPT_NAME']) == 'user_edit.php' ? 'active bg-primary' : ''; ?>" href="users.php">
                    <i class="fas fa-users me-2"></i>
                    Kullanıcılar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'campaigns.php' || basename($_SERVER['SCRIPT_NAME']) == 'campaign_edit.php' ? 'active bg-primary' : ''; ?>" href="campaigns.php">
                    <i class="fas fa-percentage me-2"></i>
                    Kampanyalar
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white text-uppercase">
            <span>İşlem Simülasyonu</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'payment_simulator.php' ? 'active bg-primary' : ''; ?>" href="payment_simulator.php">
                    <i class="fas fa-file-alt me-2"></i>
                    POS Simülatörü
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'great_transactions.php' ? 'active bg-primary' : ''; ?>" href="great_transactions.php">
                    <i class="fas fa-star me-2"></i>
                    GREAT İşlemler
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-white text-uppercase">
            <span>Ayarlar</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'site_settings.php' ? 'active bg-primary' : ''; ?>" href="site_settings.php">
                    <i class="fas fa-cog me-2"></i>
                    Site Ayarları
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white <?php echo basename($_SERVER['SCRIPT_NAME']) == 'profile.php' ? 'active bg-primary' : ''; ?>" href="profile.php">
                    <i class="fas fa-user me-2"></i>
                    Profilim
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="../index.php" target="_blank">
                    <i class="fas fa-external-link-alt me-2"></i>
                    Siteyi Görüntüle
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Çıkış Yap
                </a>
            </li>
        </ul>
    </div>
</nav>