<?php
include_once('config/config.php');

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sipariş ID'si kontrolü
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$reason = isset($_GET['reason']) ? htmlspecialchars($_GET['reason']) : 'Bilinmeyen hata';

// Sipariş bilgilerini al (varsa)
$order = [];
if ($order_id > 0) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Failure page error: ' . $e->getMessage());
    }
}

$page_title = 'Ödeme Başarısız';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Yemeksepeti</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff5a5f;
            --secondary-color: #007bff;
            --danger-color: #dc3545;
        }
        body {
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .failure-container {
            max-width: 600px;
            width: 100%;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            text-align: center;
            padding: 2rem;
        }
        .failure-header {
            margin-bottom: 2rem;
        }
        .failure-icon {
            font-size: 4rem;
            color: var(--danger-color);
            margin-bottom: 1.5rem;
        }
        .failure-message {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            color: #333;
        }
        .failure-reason {
            padding: 1rem;
            background-color: #f8d7da;
            border-radius: 5px;
            margin-bottom: 2rem;
            font-weight: 500;
            color: var(--danger-color);
        }
        .order-details {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .order-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .order-label {
            color: #666;
            font-weight: 500;
        }
        .order-value {
            font-weight: 600;
            text-align: right;
        }
        .action-buttons {
            margin-top: 2rem;
        }
        .security-note {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 2rem;
            border-left: 4px solid #6c757d;
            text-align: left;
        }
        .security-note p {
            margin-bottom: 0;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="failure-container">
        <div class="failure-header">
            <div class="failure-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h3>Ödeme İşlemi Başarısız</h3>
            <p>Ödemeniz işlenirken bir hata oluştu.</p>
        </div>
        
        <div class="failure-reason">
            <p class="mb-0"><?php echo $reason; ?></p>
        </div>
        
        <?php if ($order): ?>
        <div class="order-details">
            <div class="order-row">
                <div class="order-label">Sipariş No:</div>
                <div class="order-value">#<?php echo $order['id']; ?></div>
            </div>
            <?php if (isset($order['total'])): ?>
            <div class="order-row">
                <div class="order-label">Tutar:</div>
                <div class="order-value"><?php echo number_format($order['total'], 2); ?> TL</div>
            </div>
            <?php endif; ?>
            <?php if (isset($order['first_name']) && isset($order['last_name'])): ?>
            <div class="order-row">
                <div class="order-label">Müşteri:</div>
                <div class="order-value"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="failure-message">
            <p>Lütfen aşağıdaki adımları deneyiniz:</p>
            <ul class="text-start">
                <li>Kart bilgilerinizi kontrol ediniz.</li>
                <li>Kartınızda yeterli bakiye olduğundan emin olunuz.</li>
                <li>Farklı bir kart ile ödeme yapmayı deneyiniz.</li>
                <li>Sorun devam ederse bankanız ile iletişime geçiniz.</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary btn-lg">Ana Sayfaya Dön</a>
        </div>
        
        <div class="security-note mt-4">
            <p><i class="fas fa-shield-alt me-2"></i> Bu sayfa güvenli bir bağlantı üzerinden görüntüleniyor. Ödeme bilgileriniz tamamen güvende.</p>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>