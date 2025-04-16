<?php
require_once 'config.php';
require_admin();

// Tarih parametreleri
$date_start = isset($_GET['date_start']) ? clean_input($_GET['date_start']) : date('Y-m-01'); // Bu ayın başlangıcı
$date_end = isset($_GET['date_end']) ? clean_input($_GET['date_end']) : date('Y-m-d'); // Bugün

// Sayfalama
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// GREAT işlemleri al
$transactions = [];
$total_items = 0;
$total_amount = 0;
$monthly_stats = [];

try {
    // GREAT işlemlerinin toplam sayısı
    $sql = "SELECT COUNT(*) as total FROM payment_logs WHERE status = 'GREAT'";
    $params = [];
    
    if (!empty($date_start)) {
        $sql .= ' AND DATE(created_at) >= ?';
        $params[] = $date_start;
    }
    
    if (!empty($date_end)) {
        $sql .= ' AND DATE(created_at) <= ?';
        $params[] = $date_end;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $total_items = $stmt->fetch()['total'];
    
    // GREAT işlemlerini al
    $sql = "SELECT pl.*, o.first_name, o.last_name, o.email, o.phone 
            FROM payment_logs pl
            LEFT JOIN orders o ON pl.order_id = o.id
            WHERE pl.status = 'GREAT'";
    
    if (!empty($date_start)) {
        $sql .= ' AND DATE(pl.created_at) >= ?';
    }
    
    if (!empty($date_end)) {
        $sql .= ' AND DATE(pl.created_at) <= ?';
    }
    
    $sql .= ' ORDER BY pl.created_at DESC LIMIT ?, ?';
    $params[] = $offset;
    $params[] = $per_page;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Toplam GREAT kazanç
    $sql = "SELECT SUM(amount) as total FROM payment_logs WHERE status = 'GREAT'";
    $params = [];
    
    if (!empty($date_start)) {
        $sql .= ' AND DATE(created_at) >= ?';
        $params[] = $date_start;
    }
    
    if (!empty($date_end)) {
        $sql .= ' AND DATE(created_at) <= ?';
        $params[] = $date_end;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $total_amount = $stmt->fetch()['total'] ?: 0;
    
    // Aylık istatistikler
    $sql = "SELECT YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, SUM(amount) as total
            FROM payment_logs 
            WHERE status = 'GREAT'
            GROUP BY YEAR(created_at), MONTH(created_at)
            ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC
            LIMIT 12";
    
    $stmt = $pdo->query($sql);
    $monthly_stats = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = $e->getMessage();
}

// Sayfa başlığı
$page_title = 'GREAT İşlemler';
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">GREAT İşlemler</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="payment_logs.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-list"></i> Tüm İşlemler
                        </a>
                        <a href="payment_simulator.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-credit-card"></i> POS Simülatörü
                        </a>
                    </div>
                </div>
            </div>
            
            <?php echo display_flash_message(); ?>
            
            <!-- Genel İstatistikler -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        GREAT İşlemlerden Toplam Kazanç</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo format_money($total_amount); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-star fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Toplam GREAT İşlem Sayısı</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_items; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-receipt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtreler -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="date_start" class="form-label">Başlangıç Tarih</label>
                            <input type="date" class="form-control" id="date_start" name="date_start" value="<?php echo htmlspecialchars($date_start); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_end" class="form-label">Bitiş Tarih</label>
                            <input type="date" class="form-control" id="date_end" name="date_end" value="<?php echo htmlspecialchars($date_end); ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filtrele
                            </button>
                            <a href="great_transactions.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Sıfırla
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Aylık İstatistikler -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Aylık GREAT İşlem İstatistikleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Ay-Yıl</th>
                                    <th>İşlem Sayısı</th>
                                    <th>Toplam Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($monthly_stats)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">Henüz işlem bulunmamaktadır.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($monthly_stats as $stat): ?>
                                        <tr>
                                            <td><?php 
                                                // Ay adını Türkçe olarak göster
                                                $month_names = [
                                                    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
                                                    5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
                                                    9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
                                                ];
                                                echo $month_names[$stat['month']] . ' ' . $stat['year'];
                                            ?></td>
                                            <td><?php echo $stat['count']; ?></td>
                                            <td><?php echo format_money($stat['total']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Hata Mesajı (gerekirse) -->
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <strong>Veritabanı hatası:</strong> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <!-- İşlem Listesi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">GREAT İşlem Listesi</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Sipariş</th>
                                    <th>Müşteri</th>
                                    <th>Tutar</th>
                                    <th>Tarih</th>
                                    <th>Detaylar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">İşlem kaydı bulunamadı.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td><?php echo $tx['id']; ?></td>
                                            <td>
                                                <?php if ($tx['order_id']): ?>
                                                    <a href="order_detail.php?id=<?php echo $tx['order_id']; ?>">#<?php echo $tx['order_id']; ?></a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($tx['first_name'] && $tx['last_name']): ?>
                                                    <?php echo htmlspecialchars($tx['first_name'] . ' ' . $tx['last_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($tx['email']); ?></small>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo format_money($tx['amount']); ?></td>
                                            <td><?php echo date('d.m.Y H:i:s', strtotime($tx['created_at'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info view-details" data-id="<?php echo $tx['id']; ?>" data-details='<?php echo htmlspecialchars(json_encode([
                                                    'id' => $tx['id'],
                                                    'order_id' => $tx['order_id'],
                                                    'transaction_type' => $tx['transaction_type'],
                                                    'amount' => $tx['amount'],
                                                    'status' => $tx['status'],
                                                    'details' => json_decode($tx['details'], true),
                                                    'created_at' => date('d.m.Y H:i:s', strtotime($tx['created_at']))
                                                ])); ?>'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Sayfalama -->
            <?php
            $total_pages = ceil($total_items / $per_page);
            if ($total_pages > 1):
            ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&date_start=<?php echo urlencode($date_start); ?>&date_end=<?php echo urlencode($date_end); ?>">
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
                            <a class="page-link" href="?page=<?php echo $i; ?>&date_start=<?php echo urlencode($date_start); ?>&date_end=<?php echo urlencode($date_end); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&date_start=<?php echo urlencode($date_start); ?>&date_end=<?php echo urlencode($date_end); ?>">
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

<!-- İşlem Detayları Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">GREAT İşlem Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-5">İşlem ID:</dt>
                            <dd class="col-sm-7" id="modalTransactionId"></dd>
                            
                            <dt class="col-sm-5">Sipariş ID:</dt>
                            <dd class="col-sm-7" id="modalOrderId"></dd>
                            
                            <dt class="col-sm-5">İşlem Türü:</dt>
                            <dd class="col-sm-7" id="modalTransactionType"></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-5">Tutar:</dt>
                            <dd class="col-sm-7" id="modalAmount"></dd>
                            
                            <dt class="col-sm-5">Tarih:</dt>
                            <dd class="col-sm-7" id="modalCreatedAt"></dd>
                        </dl>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">Kart Bilgileri ve Detaylar</h6>
                    </div>
                    <div class="card-body">
                        <pre id="modalDetailsJson" class="bg-light p-3 rounded">Detay bilgisi bulunamadı.</pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>

<script>
// İşlem detaylarını göster
document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function() {
        const details = JSON.parse(this.getAttribute('data-details'));
        
        // Modal içeriğini doldur
        document.getElementById('modalTransactionId').textContent = details.id;
        document.getElementById('modalOrderId').textContent = details.order_id ? '#' + details.order_id : '-';
        document.getElementById('modalTransactionType').textContent = details.transaction_type;
        document.getElementById('modalAmount').textContent = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(details.amount);
        document.getElementById('modalCreatedAt').textContent = details.created_at;
        
        // JSON detayları göster
        if (details.details) {
            document.getElementById('modalDetailsJson').textContent = JSON.stringify(details.details, null, 2);
        } else {
            document.getElementById('modalDetailsJson').textContent = 'Detay bilgisi bulunamadı.';
        }
        
        // Modal'ı göster
        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        modal.show();
    });
});
</script>