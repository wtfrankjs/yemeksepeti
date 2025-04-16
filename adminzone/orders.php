<?php
require_once '../config/config.php';
require_admin();

// Durum güncelleme
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = intval($_POST['order_id']);
    $status = clean_input($_POST['status']);
    
    try {
        $stmt = $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $order_id]);
        
        set_flash_message('success', 'Sipariş durumu başarıyla güncellendi.');
    } catch (PDOException $e) {
        set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
    }
    
    redirect('admin/orders.php');
}

// Filtre parametreleri
$status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Sayfalama
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Siparişleri al
$orders = [];
$total_items = 0;

try {
    // Sorgu oluşturma
    $sql = 'SELECT o.*, u.email FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            WHERE 1=1';
    $params = [];
    
    // Filtreler
    if (!empty($status)) {
        $sql .= ' AND o.status = ?';
        $params[] = $status;
    }
    
    if (!empty($search)) {
        $sql .= ' AND (o.id LIKE ? OR o.first_name LIKE ? OR o.last_name LIKE ? OR o.email LIKE ? OR o.phone LIKE ?)';
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Toplam sipariş sayısı
    $count_sql = str_replace('o.*, u.email', 'COUNT(*) as total', $sql);
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_items = $stmt->fetch()['total'];
    
    // Sayfalama için limit
    $sql .= ' ORDER BY o.created_at DESC LIMIT ?, ?';
    $params[] = $offset;
    $params[] = $per_page;
    
    // Siparişleri getir
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
}

// Sayfa başlığı
$page_title = 'Sipariş Yönetimi';
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Sipariş Yönetimi</h1>
            </div>
            
            <?php echo display_flash_message(); ?>
            
            <!-- Filtreler -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Arama</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Sipariş no, müşteri adı, telefon, e-posta...">
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Sipariş Durumu</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tüm Durumlar</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Hazırlanıyor</option>
                                <option value="shipped" <?php echo $status == 'shipped' ? 'selected' : ''; ?>>Yolda</option>
                                <option value="delivered" <?php echo $status == 'delivered' ? 'selected' : ''; ?>>Teslim Edildi</option>
                                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filtrele
                            </button>
                            <a href="orders.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Sıfırla
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sipariş Listesi -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Sipariş No</th>
                            <th>Müşteri</th>
                            <th>Telefon</th>
                            <th>Tutar</th>
                            <th>Ödeme Yöntemi</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Sipariş bulunamadı.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['phone']); ?></td>
                                    <td><?php echo number_format($order['total'], 2, ',', '.'); ?> TL</td>
                                    <td>
                                        <?php
                                        switch ($order['payment_method']) {
                                            case 'credit_card':
                                                echo 'Kredi Kartı';
                                                break;
                                            case 'cash':
                                                echo 'Kapıda Nakit';
                                                break;
                                            case 'pos':
                                                echo 'Kapıda Kredi Kartı';
                                                break;
                                            default:
                                                echo $order['payment_method'];
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <form action="" method="post" class="status-form">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="status" class="form-select form-select-sm status-select" data-original-status="<?php echo $order['status']; ?>">
                                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Hazırlanıyor</option>
                                                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Yolda</option>
                                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Teslim Edildi</option>
                                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="#" class="btn btn-sm btn-secondary print-order" data-order-id="<?php echo $order['id']; ?>">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Sayfalama -->
            <?php
            $total_pages = ceil($total_items / $per_page);
            if ($total_pages > 1):
            ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-chevron-left"></i></span>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-chevron-right"></i></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Durum değişikliğinde formu otomatik gönder
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        const originalStatus = this.getAttribute('data-original-status');
        const newStatus = this.value;
        
        if (originalStatus !== newStatus) {
            if (confirm('Sipariş durumunu değiştirmek istediğinize emin misiniz?')) {
                this.closest('form').submit();
            } else {
                // Değişikliği iptal et, seçimi eski haline getir
                this.value = originalStatus;
            }
        }
    });
});

// Sipariş yazdırma
document.querySelectorAll('.print-order').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const orderId = this.getAttribute('data-order-id');
        window.open('order_print.php?id=' + orderId, '_blank', 'width=800,height=600');
    });
});
</script>