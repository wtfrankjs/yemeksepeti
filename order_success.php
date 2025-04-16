<?php
require_once 'config/config.php';

// Sipariş başarılı bilgisi kontrolü
if (!isset($_SESSION['order_success'])) {
    header('Location: index.php');
    exit;
}

$order_id = $_SESSION['order_success']['order_id'];
$total = $_SESSION['order_success']['total'];

// Sipariş detaylarını al
try {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        // Sipariş bulunamadı
        header('Location: index.php');
        exit;
    }
    
    // Sipariş ürünlerini al
    $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Hata durumunda ana sayfaya yönlendir
    header('Location: index.php');
    exit;
}

// Session'daki sipariş bilgisini temizle
unset($_SESSION['order_success']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Başarılı - Yemeksepeti</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .success-icon {
            font-size: 5rem;
            color: #4CAF50;
        }
        .order-details {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        .order-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .order-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="border-bottom shadow-sm">
        <div class="container py-3">
            <div class="d-flex justify-content-between align-items-center">
                <!-- Logo -->
                <a href="index.php" class="navbar-brand"><img class="logocss" src="images/logo.png" alt="Yemeksepeti"></a>
                
                <?php if (is_logged_in()): ?>
                    <!-- Kullanıcı Oturumu Açık -->
                    <div class="d-flex align-items-center gap-3">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-2"></i>Hesabım
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="profile.php">Profilim</a></li>
                                <li><a class="dropdown-item" href="orders.php">Siparişlerim</a></li>
                                <li><a class="dropdown-item" href="addresses.php">Adreslerim</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Çıkış Yap</a></li>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Oturum Açılmamış -->
                    <div class="d-flex align-items-center gap-3">
                        <a href="login.php" class="btn btn-outline-light rounded-pill px-4 py-2 btn-hover">Giriş Yap</a>
                        <a href="register.php" class="btn btn-red rounded-pill px-4 py-2 btn-hover">Kayıt Ol</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Başarı Mesajı -->
                <div class="text-center mb-4">
                    <i class="fas fa-check-circle success-icon mb-3"></i>
                    <h1 class="display-4">Siparişiniz Alındı!</h1>
                    <p class="lead">Siparişiniz başarıyla oluşturuldu. Siparişinizin durumu hakkında bilgilendirileceksiniz.</p>
                    <div class="alert alert-success">
                        <strong>Sipariş Numaranız:</strong> #<?php echo $order_id; ?>
                    </div>
                </div>
                
                <!-- Sipariş Detayları -->
                <div class="order-details">
                    <h3 class="mb-3">Sipariş Detayları</h3>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Teslimat Bilgileri</h5>
                            <p class="mb-1"><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                            <p class="mb-1"><strong>Telefon:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                            <p class="mb-1"><strong>Adres:</strong> <?php echo htmlspecialchars($order['address']); ?></p>
                            <?php if (!empty($order['address_detail'])): ?>
                                <p class="mb-1"><strong>Adres Detayı:</strong> <?php echo htmlspecialchars($order['address_detail']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h5>Sipariş Bilgileri</h5>
                            <p class="mb-1"><strong>Tarih:</strong> <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>
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
                            <p class="mb-1"><strong>Durum:</strong> 
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
                                    default:
                                        echo $order['status'];
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <h5>Sipariş Edilen Ürünler</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th class="text-center">Fiyat</th>
                                    <th class="text-center">Adet</th>
                                    <th class="text-end">Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td class="text-center"><?php echo number_format($item['price'], 2, ',', '.'); ?> TL</td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end"><?php echo number_format($item['subtotal'], 2, ',', '.'); ?> TL</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Ara Toplam:</strong></td>
                                    <td class="text-end"><?php echo number_format($order['subtotal'], 2, ',', '.'); ?> TL</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Teslimat Ücreti:</strong></td>
                                    <td class="text-end"><?php echo number_format($order['delivery_fee'], 2, ',', '.'); ?> TL</td>
                                </tr>
                                <?php if ($order['discount'] > 0): ?>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>İndirim:</strong></td>
                                        <td class="text-end">-<?php echo number_format($order['discount'], 2, ',', '.'); ?> TL</td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Toplam:</strong></td>
                                    <td class="text-end"><strong><?php echo number_format($order['total'], 2, ',', '.'); ?> TL</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <!-- Butonlar -->
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-primary me-2">
                        <i class="fas fa-home me-2"></i>Ana Sayfaya Dön
                    </a>
                    <?php if (is_logged_in()): ?>
                        <a href="orders.php" class="btn btn-outline-primary">
                            <i class="fas fa-list-alt me-2"></i>Siparişlerim
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Yemeksepeti</h5>
                    <p>Türkiye'nin online yemek ve market siparişi platformu</p>
                </div>
                <div class="col-md-4">
                    <h5>Hızlı Erişim</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Ana Sayfa</a></li>
                        <li><a href="#">Hakkımızda</a></li>
                        <li><a href="#">İletişim</a></li>
                        <li><a href="#">Yardım</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>İletişim</h5>
                    <p>
                        <i class="fas fa-envelope me-2"></i> info@yemeksepeti.com<br>
                        <i class="fas fa-phone me-2"></i> 0850 123 4567
                    </p>
                    <div class="mt-3">
                        <a href="#" class="text-dark me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-dark me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-dark me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Yemeksepeti. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sepet verisini temizle
        localStorage.removeItem('cart');
    </script>
</body>
</html>