<?php
require_once 'config.php';
require_admin();

// İstatistikler
$stats = [
    'orders' => 0,
    'products' => 0,
    'users' => 0,
    'revenue' => 0,
    'profit' => 0, // GREAT işlemlerden gelen kazanç
];

// Son siparişler ve işlemler
$recent_orders = [];
$recent_transactions = [];

try {
    // Sipariş sayısı
    $stmt = $pdo->query('SELECT COUNT(*) FROM orders');
    $stats['orders'] = $stmt->fetchColumn();
    
    // Ürün sayısı
    $stmt = $pdo->query('SELECT COUNT(*) FROM products');
    $stats['products'] = $stmt->fetchColumn();
    
    // Kullanıcı sayısı
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $stats['users'] = $stmt->fetchColumn();
    
    // Toplam gelir (onaylanan siparişler)
    $stmt = $pdo->query('SELECT SUM(total) FROM orders WHERE status != "cancelled"');
    $stats['revenue'] = $stmt->fetchColumn() ?: 0;
    
    // GREAT işlemlerden gelen kazanç
    $stmt = $pdo->query('SELECT SUM(total) FROM orders WHERE status = "completed" AND payment_status = "GREAT"');
    $stats['profit'] = $stmt->fetchColumn() ?: 0;
    
    // Son 5 sipariş
    $stmt = $pdo->query('SELECT o.*, u.email FROM orders o 
                        LEFT JOIN users u ON o.user_id = u.id 
                        ORDER BY o.created_at DESC LIMIT 5');
    $recent_orders = $stmt->fetchAll();
    
    // Son 5 ödeme işlemi
    $stmt = $pdo->query('SELECT * FROM payment_logs 
                        ORDER BY created_at DESC LIMIT 5');
    $recent_transactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
}

// Sayfa başlığı
$page_title = 'Admin Paneli';
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/admin-sidebar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
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
            
            <!-- Hata Mesajı (gerekirse) -->
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <strong>Veritabanı hatası:</strong> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <!-- İstatistikler -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Toplam Sipariş</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['orders']; ?></div>
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
                                        Toplam Kazanç</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo format_money($stats['revenue']); ?></div>
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
                                        GREAT Kazanç</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo format_money($stats['profit']); ?></div>
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
                                        Toplam Kullanıcı</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['users']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Son Siparişler -->
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Son Siparişler</h6>
                            <a href="orders.php" class="btn btn-sm btn-primary">Tümünü Gör</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Müşteri</th>
                                            <th>Tutar</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_orders)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">Henüz sipariş bulunmamaktadır.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_orders as $order): ?>
                                                <tr>
                                                    <td>#<?php echo $order['id']; ?></td>
                                                    <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                                                    <td><?php echo format_money($order['total']); ?></td>
                                                    <td>
                                                        <?php
                                                        switch ($order['status']) {
                                                            case 'pending':
                                                                echo '<span class="badge bg-warning text-dark">Beklemede</span>';
                                                                break;
                                                            case 'processing':
                                                                echo '<span class="badge bg-info text-dark">Hazırlanıyor</span>';
                                                                break;
                                                            case 'shipped':
                                                                echo '<span class="badge bg-primary">Yolda</span>';
                                                                break;
                                                            case 'delivered':
                                                                echo '<span class="badge bg-success">Teslim Edildi</span>';
                                                                break;
                                                            case 'cancelled':
                                                                echo '<span class="badge bg-danger">İptal Edildi</span>';
                                                                break;
                                                            case 'completed':
                                                                echo '<span class="badge bg-success">Tamamlandı</span>';
                                                                break;
                                                            default:
                                                                echo $order['status'];
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
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
                
                <!-- Son Ödeme İşlemleri -->
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Son Ödeme İşlemleri</h6>
                            <a href="payment_logs.php" class="btn btn-sm btn-primary">Tümünü Gör</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Sipariş</th>
                                            <th>İşlem Türü</th>
                                            <th>Durum</th>
                                            <th>Tarih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_transactions)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">Henüz işlem bulunmamaktadır.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_transactions as $tx): ?>
                                                <tr>
                                                    <td><?php echo $tx['id']; ?></td>
                                                    <td>#<?php echo $tx['order_id']; ?></td>
                                                    <td><?php echo $tx['transaction_type']; ?></td>
                                                    <td>
                                                        <?php
                                                        switch ($tx['status']) {
                                                            case 'success':
                                                                echo '<span class="badge bg-success">Başarılı</span>';
                                                                break;
                                                            case 'failed':
                                                                echo '<span class="badge bg-danger">Başarısız</span>';
                                                                break;
                                                            case 'pending':
                                                                echo '<span class="badge bg-warning text-dark">Beklemede</span>';
                                                                break;
                                                            case 'GREAT':
                                                                echo '<span class="badge bg-primary">GREAT</span>';
                                                                break;
                                                            default:
                                                                echo $tx['status'];
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo date('d.m.Y H:i', strtotime($tx['created_at'])); ?></td>
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

<?php include 'includes/admin-footer.php'; ?>