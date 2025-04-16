<?php
require_once '../config/config.php';
require_admin();

// Sipariş ID'si
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Sipariş bulunamadıysa yönlendir
if ($order_id <= 0) {
    set_flash_message('danger', 'Geçersiz sipariş ID\'si.');
    redirect('admin/orders.php');
}

// Sipariş bilgilerini al
try {
    // Sipariş detayları
    $stmt = $pdo->prepare('SELECT o.*, u.email as user_email 
                          FROM orders o
                          LEFT JOIN users u ON o.user_id = u.id
                          WHERE o.id = ?');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    // Sipariş bulunamadıysa yönlendir
    if (!$order) {
        set_flash_message('danger', 'Sipariş bulunamadı.');
        redirect('admin/orders.php');
    }
    
    // Sipariş ürünleri
    $stmt = $pdo->prepare('SELECT oi.*, p.name, p.image, p.sku
                          FROM order_items oi
                          LEFT JOIN products p ON oi.product_id = p.id
                          WHERE oi.order_id = ?');
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
    redirect('admin/orders.php');
}

// Durum güncelleme
if (isset($_POST['update_status']) && isset($_POST['status'])) {
    $status = clean_input($_POST['status']);
    
    try {
        $stmt = $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $order_id]);
        
        set_flash_message('success', 'Sipariş durumu başarıyla güncellendi.');
        redirect('admin/order_detail.php?id=' . $order_id);
    } catch (PDOException $e) {
        set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
    }
}

// Sayfa başlığı
$page_title = 'Sipariş Detayı #' . $order_id;
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Sipariş Detayı #<?php echo $order_id; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="orders.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Siparişlere Dön
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary print-order" data-order-id="<?php echo $order_id; ?>">
                            <i class="fas fa-print"></i> Yazdır
                        </button>
                    </div>
                </div>
            </div>
            
            <?php echo display_flash_message(); ?>
            
            <!-- Sipariş Bilgileri -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Sipariş Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 150px;">Sipariş No</th>
                                    <td>#<?php echo $order['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Tarih</th>
                                    <td><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Durum</th>
                                    <td>
                                        <form action="" method="post" class="d-flex align-items-center">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="status" class="form-select form-select-sm me-2" style="width: 150px;">
                                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Hazırlanıyor</option>
                                                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Yolda</option>
                                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Teslim Edildi</option>
                                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Güncelle</button>
                                        </form>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Ödeme Yöntemi</th>
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
                                </tr>
                                <tr>
                                    <th>Ödeme Durumu</th>
                                    <td>
                                        <?php if ($order['payment_status'] == 'paid'): ?>
                                            <span class="badge bg-success">Ödendi</span>
                                        <?php elseif ($order['payment_status'] == 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Beklemede</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Ödenmedi</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Müşteri Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 150px;">Ad Soyad</th>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>E-posta</th>
                                    <td><?php echo htmlspecialchars($order['email'] ?: $order['user_email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Telefon</th>
                                    <td><?php echo htmlspecialchars($order['phone']); ?></td>
                                </tr>
                                <tr>
                                    <th>Adres</th>
                                    <td><?php echo htmlspecialchars($order['address']); ?></td>
                                </tr>
                                <tr>
                                    <th>Adres Detayı</th>
                                    <td><?php echo htmlspecialchars($order['address_detail']); ?></td>
                                </tr>
                                <tr>
                                    <th>Notlar</th>
                                    <td><?php echo !empty($order['notes']) ? htmlspecialchars($order['notes']) : '<em>Not belirtilmemiş</em>'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sipariş Ürünleri -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Sipariş Ürünleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Resim</th>
                                    <th>Ürün</th>
                                    <th>Stok Kodu</th>
                                    <th class="text-end">Birim Fiyat</th>
                                    <th class="text-center">Adet</th>
                                    <th class="text-end">Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($item['image'])): ?>
                                                <img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: contain;">
                                            <?php else: ?>
                                                <img src="../images/placeholder.jpg" alt="Ürün Görseli" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: contain;">
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo !empty($item['sku']) ? htmlspecialchars($item['sku']) : '-'; ?></td>
                                        <td class="text-end"><?php echo number_format($item['price'], 2, ',', '.'); ?> TL</td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end"><?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.'); ?> TL</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Ara Toplam:</strong></td>
                                    <td class="text-end"><?php echo number_format($order['subtotal'], 2, ',', '.'); ?> TL</td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Teslimat Ücreti:</strong></td>
                                    <td class="text-end"><?php echo number_format($order['delivery_fee'], 2, ',', '.'); ?> TL</td>
                                </tr>
                                <?php if ($order['discount'] > 0): ?>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>İndirim:</strong></td>
                                    <td class="text-end">-<?php echo number_format($order['discount'], 2, ',', '.'); ?> TL</td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($order['tip'] > 0): ?>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Bahşiş:</strong></td>
                                    <td class="text-end"><?php echo number_format($order['tip'], 2, ',', '.'); ?> TL</td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Genel Toplam:</strong></td>
                                    <td class="text-end"><strong><?php echo number_format($order['total'], 2, ',', '.'); ?> TL</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Sipariş yazdırma
document.querySelector('.print-order').addEventListener('click', function(e) {
    e.preventDefault();
    const orderId = this.getAttribute('data-order-id');
    window.open('order_print.php?id=' + orderId, '_blank', 'width=800,height=600');
});
</script>