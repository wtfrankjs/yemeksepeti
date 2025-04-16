<?php
include_once('config/config.php');

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sipariş ID'si kontrolü
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Sipariş bilgilerini al (varsa)
$order = [];
if ($order_id > 0) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Success page error: ' . $e->getMessage());
    }
}

$page_title = 'Ödeme Başarılı';
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
            --success-color: #28a745;
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
        .success-container {
            max-width: 600px;
            width: 100%;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            text-align: center;
            padding: 2rem;
        }
        .success-header {
            margin-bottom: 2rem;
        }
        .success-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 1.5rem;
        }
        .success-message {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            color: #333;
        }
        .success-details {
            padding: 1rem;
            background-color: #d4edda;
            border-radius: 5px;
            margin-bottom: 2rem;
            font-weight: 500;
            color: var(--success-color);
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
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>Ödeme İşlemi Başarılı</h3>
            <p>Siparişiniz başarıyla tamamlandı.</p>
        </div>
        
        <div class="success-details">
            <p class="mb-0">Ödeme işleminiz başarıyla gerçekleştirildi.</p>
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
            <?php if (isset($order['created_at'])): ?>
            <div class="order-row">
                <div class="order-label">Tarih:</div>
                <div class="order-value"><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="success-message">
            <p>Sipariş bilgileriniz e-posta adresinize gönderilecektir.</p>
            <p>Bizi tercih ettiğiniz için teşekkür ederiz!</p>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary btn-lg">Ana Sayfaya Dön</a>
        </div>
        
        <div class="security-note mt-4">
            <p><i class="fas fa-shield-alt me-2"></i> Bu işlem güvenli bir bağlantı üzerinden gerçekleştirildi. Ödeme bilgileriniz tamamen güvende.</p>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>