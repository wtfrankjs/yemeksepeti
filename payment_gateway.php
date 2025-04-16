<?php
ob_start();
require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['order_id'], $_SESSION['payment_data'])) {
    set_flash_message('danger','Ödeme bilgileri bulunamadı.');
    redirect('checkout.php');
}

$order_id     = $_SESSION['order_id'];
$payment_data = $_SESSION['payment_data'];

try {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    if (!$order) throw new Exception('Sipariş bulunamadı.');
} catch (Exception $e) {
    set_flash_message('danger','Hata: '.$e->getMessage());
    redirect('checkout.php');
}

function maskCardNumber($num) {
    $digits = preg_replace('/\D/','',$num);
    return strlen($digits) < 4 
        ? $digits 
        : str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
}

$page_title = 'Ödeme İşlemi';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $page_title;?> - Yemeksepeti</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff5a5f;
            --secondary-color: #007bff;
        }
        body {
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .payment-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .payment-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            text-align: center;
            position: relative;
        }
        .bank-icon {
            position: absolute;
            top: 1rem;
            left: 1rem;
            font-size: 2rem;
        }
        .secure-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
        }
        .payment-body {
            padding: 2rem;
        }
        .payment-info {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .card-details {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background-color: #f9f9f9;
        }
        .card-info {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        .card-number {
            font-size: 1.2rem;
            letter-spacing: 2px;
            margin-left: 1rem;
        }
        .payment-status {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        .status-pending {
            background-color: #ffc107;
        }
        .status-approved {
            background-color: #28a745;
        }
        .status-error {
            background-color: #dc3545;
        }
        .payment-options {
            margin-top: 2rem;
        }
        .test-buttons {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px dashed #ccc;
        }
        .option-button {
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }
        .option-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        .btn-danger {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        #confirmationMessage {
            display: none;
            margin-top: 1.5rem;
        }
        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            padding: 5px;
            padding-bottom: 10px;
            background-color: #f9f9f9;
            border-radius: 10px;
            border: 1px solid #eee;
        }
        .logo-container img {
            height: 50px;
            width: auto;
            opacity: 0.85;
            transition: opacity 0.3s;
        }
        .logo-container img:hover {
            opacity: 1;
        }
        .processing-animation {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            margin: 2rem 0;
        }
        .loader {
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--secondary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
            margin-bottom: 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .security-badge {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            margin-top: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .security-badge i {
            font-size: 1.5rem;
            color: #28a745;
            margin-right: 10px;
        }
        .security-badge p {
            margin: 0;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="payment-container" data-order-id="<?php echo $order_id;?>">
        <div class="payment-header">
            <i class="fas fa-university bank-icon"></i>
            <i class="fas fa-lock secure-icon"></i>
            <h3>Ödeme İşlemi</h3>
            <p class="mb-0">Yemeksepeti Güvenli Ödeme Sistemi</p>
        </div>
        <div class="payment-body">
            <div class="payment-info row">
                <div class="col-md-6">
                    <h5>Sipariş Bilgileri</h5>
                    <p><strong>Sipariş No:</strong> #<?php echo $order_id; ?><br>
                       <strong>Tarih:</strong> <?php echo date('d.m.Y H:i'); ?><br>
                       <strong>Toplam Tutar:</strong> <?php echo format_money($order['total']); ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Müşteri Bilgileri</h5>
                    <p><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($order['first_name'].' '.$order['last_name']); ?><br>
                       <strong>E-posta:</strong> <?php echo htmlspecialchars($order['email']); ?><br>
                       <strong>Telefon:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                </div>
            </div>
            <div class="card-details">
                <h5>Kart Bilgileri</h5>
                <div class="card-info"><i class="fas fa-credit-card fa-2x"></i>
                    <span class="card-number"><?php echo maskCardNumber($payment_data['card_number']); ?>
                        <small class="text-muted">(<?php echo $payment_data['expiry_date']; ?>)</small>
                    </span>
                </div>
                <p><strong>Kart Sahibi:</strong> <?php echo htmlspecialchars($payment_data['card_holder']); ?></p>
            </div>
            <div id="processingScreen" class="processing-animation">
                <div class="loader"></div>
                <h5>İşleminiz Gerçekleştiriliyor</h5>
                <p class="text-muted">Lütfen bekleyin...</p>
            </div>
            <div id="confirmationMessage" class="alert alert-success" style="display:none;">
                <i class="fas fa-check-circle me-2"></i><span id="messageText"></span>
            </div>
            <div class="security-badge"><i class="fas fa-shield-alt"></i>
                <p>256-bit SSL şifreleme ile korunmaktadır.</p>
            </div>
            <div class="logo-container"><img src="images/cards.png" alt="Cards"></div>
        </div>
    </div>
</div>
<input type="hidden" id="order_id" value="<?php echo $order_id;?>">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/payment_gateway.js?v=11"></script>
</body>
</html>
<?php ob_end_flush(); ?>