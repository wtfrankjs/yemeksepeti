<?php
require_once '../config/config.php';
require_admin();

// Sipariş ID'si
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Sipariş bulunamadıysa yönlendir
if ($order_id <= 0) {
    die('Geçersiz sipariş ID\'si.');
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
        die('Sipariş bulunamadı.');
    }
    
    // Sipariş ürünleri
    $stmt = $pdo->prepare('SELECT oi.*, p.name, p.image, p.sku
                          FROM order_items oi
                          LEFT JOIN products p ON oi.product_id = p.id
                          WHERE oi.order_id = ?');
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş #<?php echo $order_id; ?> - Yazdır</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-size: 14px;
        }
        .header {
            padding: 20px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            text-align: center;
        }
        .table-items th, .table-items td {
            padding: 8px;
        }
        .print-btn {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        @media print {
            .print-btn {
                display: none;
            }
            @page {
                margin: 0.5cm;
            }
            body {
                margin: 1cm;
            }
        }
    </style>
</head>
<body>
    <button class="btn btn-primary print-btn" onclick="window.print();">Yazdır</button>
    
    <div class="container">
        <div class="header">
            <div class="row align-items-center">
                <div class="col-6">
                    <img src="../images/logo.png" alt="Yemeksepeti" style="max-height: 50px;">
                </div>
                <div class="col-6 text-end">
                    <h4>Sipariş #<?php echo $order['id']; ?></h4>
                    <p class="mb-0">Tarih: <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-6">
                <h5>Müşteri Bilgileri</h5>
                <p class="mb-1"><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                <p class="mb-1"><strong>E-posta:</strong> <?php echo htmlspecialchars($order['email'] ?: $order['user_email']); ?></p>
                <p class="mb-1"><strong>Telefon:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
            </div>
            <div class="col-6">
                <h5>Teslimat Adresi</h5>
                <p><?php echo htmlspecialchars($order['address']); ?></p>
                <p><?php echo htmlspecialchars($order['address_detail']); ?></p>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-6">
                <h5>Ödeme Bilgileri</h5>
                <p class="mb-1"><strong>Ödeme Yöntemi:</strong> 
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
                </p>
                <p class="mb-1"><strong>Ödeme Durumu:</strong> 
                    <?php 
                    switch ($order['payment_status']) {
                        case 'paid':
                            echo 'Ödendi';
                            break;
                        case 'pending':
                            echo 'Beklemede';
                            break;
                        default:
                            echo 'Ödenmedi';
                    }
                    ?>
                </p>
            </div>
            <div class="col-6">
                <h5>Sipariş Durumu</h5>
                <p>
                    <?php 
                    switch ($order['status']) {
                        case 'pending':
                            echo 'Beklemede';
                            break;
                        case 'processing':
                            echo 'Hazırlanıyor';
                            break;
                        case 'shipped':
                            echo 'Yolda';
                            break;
                        case 'delivered':
                            echo 'Teslim Edildi';
                            break;
                        case 'cancelled':
                            echo 'İptal Edildi';
                            break;
                        default:
                            echo $order['status'];
                    }
                    ?>
                </p>
                <?php if (!empty($order['notes'])): ?>
                <h5>Notlar</h5>
                <p><?php echo htmlspecialchars($order['notes']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <h5>Sipariş Detayları</h5>
        <table class="table table-bordered table-items">
            <thead>
                <tr>
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
                    <td colspan="4" class="text-end"><strong>Ara Toplam:</strong></td>
                    <td class="text-end"><?php echo number_format($order['subtotal'], 2, ',', '.'); ?> TL</td>
                </tr>
                <tr>
                    <td colspan="4" class="text-end"><strong>Teslimat Ücreti:</strong></td>
                    <td class="text-end"><?php echo number_format($order['delivery_fee'], 2, ',', '.'); ?> TL</td>
                </tr>
                <?php if ($order['discount'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end"><strong>İndirim:</strong></td>
                    <td class="text-end">-<?php echo number_format($order['discount'], 2, ',', '.'); ?> TL</td>
                </tr>
                <?php endif; ?>
                <?php if ($order['tip'] > 0): ?>
                <tr>
                    <td colspan="4" class="text-end"><strong>Bahşiş:</strong></td>
                    <td class="text-end"><?php echo number_format($order['tip'], 2, ',', '.'); ?> TL</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="4" class="text-end"><strong>Genel Toplam:</strong></td>
                    <td class="text-end"><strong><?php echo number_format($order['total'], 2, ',', '.'); ?> TL</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="footer">
            <p>Bu fatura <?php echo date('d.m.Y H:i'); ?> tarihinde oluşturulmuştur.</p>
            <p>&copy; 2025 Yemeksepeti. Tüm hakları saklıdır.</p>
        </div>
    </div>
    
    <script>
        // Otomatik yazdırma
        window.onload = function() {
            // 1 saniye sonra yazdırma işlemini başlat
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>