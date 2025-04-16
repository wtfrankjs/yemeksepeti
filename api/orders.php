<?php
/**
 * Siparişler API - RESTful API Endpoint
 * 
 * HTTP Methodları:
 * GET /api/orders - Kullanıcının siparişlerini listele
 * GET /api/orders/123 - ID'si 123 olan siparişi getir
 * POST /api/orders - Yeni sipariş oluştur
 * PUT /api/orders/123 - ID'si 123 olan siparişi güncelle (admin)
 */

// Config dosyasını include et
require_once '../config/config.php';

// CORS ayarları (Cross-Origin Resource Sharing)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// HTTP methodunu al
$request_method = $_SERVER["REQUEST_METHOD"];

// Sipariş ID'si varsa al
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// API için cevap hazırla
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// İstek methoduna göre işlem yap
switch ($request_method) {
    case 'GET':
        if ($id > 0) {
            // Tek sipariş getir
            getOrder($id);
        } else {
            // Siparişleri listele
            getOrders();
        }
        break;
    
    case 'POST':
        // Yeni sipariş oluştur
        createOrder();
        break;
    
    case 'PUT':
        // Sipariş güncelle (admin)
        if ($id > 0 && is_admin()) {
            updateOrder($id);
        } else {
            http_response_code(401); // Unauthorized
            $response['message'] = 'Bu işlem için yetkiniz bulunmamaktadır.';
            echo json_encode($response);
        }
        break;
    
    default:
        // Desteklenmeyen method
        http_response_code(405); // Method Not Allowed
        $response['message'] = 'Desteklenmeyen HTTP methodu.';
        echo json_encode($response);
        break;
}

/**
 * Siparişleri listele
 */
function getOrders() {
    global $pdo, $response;
    
    try {
        // Kullanıcı oturum kontrolü
        $user_id = 0;
        $is_admin_request = false;
        
        if (is_logged_in()) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (is_admin()) {
            $is_admin_request = true;
        }
        
        // Filtre parametreleri
        $status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        // Sorgu oluşturma
        if ($is_admin_request) {
            // Admin tüm siparişleri görebilir
            $sql = 'SELECT o.*, u.email FROM orders o 
                    LEFT JOIN users u ON o.user_id = u.id 
                    WHERE 1=1';
            $params = [];
        } else {
            // Normal kullanıcı sadece kendi siparişlerini görebilir
            if ($user_id > 0) {
                $sql = 'SELECT o.* FROM orders o WHERE o.user_id = ?';
                $params = [$user_id];
            } else {
                // Oturum açılmamış, sipariş listesi boş
                http_response_code(200);
                $response['success'] = true;
                $response['message'] = 'Sipariş bulunamadı.';
                $response['data'] = [
                    'total' => 0,
                    'items' => []
                ];
                echo json_encode($response);
                return;
            }
        }
        
        // Filtreler
        if (!empty($status)) {
            $sql .= ' AND o.status = ?';
            $params[] = $status;
        }
        
        // Sıralama
        $sql .= ' ORDER BY o.created_at DESC';
        
        // Limitleme
        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
            
            if ($offset > 0) {
                $sql .= ' OFFSET ?';
                $params[] = $offset;
            }
        }
        
        // Siparişleri getir
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // Her sipariş için ürünleri getir
        foreach ($orders as &$order) {
            $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
            $stmt->execute([$order['id']]);
            $order['items'] = $stmt->fetchAll();
        }
        
        // Toplam sipariş sayısı
        $count_sql = str_replace('o.*, u.email', 'COUNT(*) as total', $sql);
        $count_sql = str_replace('o.*', 'COUNT(*) as total', $count_sql);
        $count_sql = preg_replace('/LIMIT \?(\s+OFFSET \?)?/i', '', $count_sql);
        $stmt = $pdo->prepare($count_sql);
        
        // Limit ve offset parametrelerini kaldır
        if ($limit > 0) {
            array_pop($params);
            if ($offset > 0) {
                array_pop($params);
            }
        }
        
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Başarılı cevap hazırla
        http_response_code(200);
        $response['success'] = true;
        $response['message'] = 'Siparişler başarıyla getirildi.';
        $response['data'] = [
            'total' => $total,
            'items' => $orders
        ];
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Tek sipariş getir
 */
function getOrder($id) {
    global $pdo, $response;
    
    try {
        // Kullanıcı oturum kontrolü
        $user_id = 0;
        $is_admin_request = false;
        
        if (is_logged_in()) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (is_admin()) {
            $is_admin_request = true;
        }
        
        // Sorgu oluşturma
        if ($is_admin_request) {
            // Admin tüm siparişleri görebilir
            $sql = 'SELECT o.*, u.email FROM orders o 
                    LEFT JOIN users u ON o.user_id = u.id 
                    WHERE o.id = ?';
            $params = [$id];
        } else {
            // Normal kullanıcı sadece kendi siparişlerini görebilir
            if ($user_id > 0) {
                $sql = 'SELECT o.* FROM orders o WHERE o.id = ? AND o.user_id = ?';
                $params = [$id, $user_id];
            } else {
                // Oturum açılmamış, erişim izni yok
                http_response_code(401);
                $response['message'] = 'Bu işlem için giriş yapmalısınız.';
                echo json_encode($response);
                return;
            }
        }
        
        // Siparişi getir
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch();
        
        if ($order) {
            // Sipariş ürünlerini getir
            $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
            $stmt->execute([$id]);
            $order['items'] = $stmt->fetchAll();
            
            // Başarılı cevap
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Sipariş başarıyla getirildi.';
            $response['data'] = $order;
        } else {
            // Sipariş bulunamadı
            http_response_code(404);
            $response['message'] = 'Sipariş bulunamadı.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Yeni sipariş oluştur
 */
function createOrder() {
    global $pdo, $response;
    
    try {
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Gerekli alanları kontrol et
        if (!isset($data['first_name']) || !isset($data['last_name']) || !isset($data['phone']) 
            || !isset($data['address']) || !isset($data['payment_method']) || !isset($data['items']) 
            || empty($data['items'])) {
            
            http_response_code(400);
            $response['message'] = 'Eksik veri. Ad, soyad, telefon, adres, ödeme yöntemi ve ürünler zorunludur.';
            echo json_encode($response);
            return;
        }
        
        // Kullanıcı ID'si
        $user_id = null;
        if (is_logged_in()) {
            $user_id = $_SESSION['user_id'];
        }
        
        // Sipariş toplam tutarını hesapla
        $subtotal = 0;
        $delivery_fee = 0;
        $discount = 0;
        
        foreach ($data['items'] as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                http_response_code(400);
                $response['message'] = 'Geçersiz ürün verisi.';
                echo json_encode($response);
                return;
            }
            
            // Ürün bilgilerini getir
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch();
            
            if (!$product) {
                http_response_code(400);
                $response['message'] = 'Ürün bulunamadı veya aktif değil. Ürün ID: ' . $item['product_id'];
                echo json_encode($response);
                return;
            }
            
            // Stok kontrolü
            if ($product['stock'] < $item['quantity']) {
                http_response_code(400);
                $response['message'] = 'Yetersiz stok. Ürün: ' . $product['name'] . ', Mevcut stok: ' . $product['stock'];
                echo json_encode($response);
                return;
            }
            
            // Ara toplam
            $subtotal += $product['price'] * $item['quantity'];
        }
        
        // Teslimat ücreti
        $delivery_fee = $subtotal >= 250 ? 0 : 45.99;
        
        // İndirim (varsayılan %10)
        $discount_rate = $data['discount_rate'] ?? 0.10;
        $discount = $subtotal * $discount_rate;
        
        // Toplam tutar
        $total = $subtotal + $delivery_fee - $discount;
        
        // Sipariş oluştur
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare('INSERT INTO orders (user_id, first_name, last_name, email, phone, address, address_detail, 
                                payment_method, subtotal, delivery_fee, discount, total, status, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        $stmt->execute([
            $user_id,
            $data['first_name'],
            $data['last_name'],
            $data['email'] ?? null,
            $data['phone'],
            $data['address'],
            $data['address_detail'] ?? null,
            $data['payment_method'],
            $subtotal,
            $delivery_fee,
            $discount,
            $total,
            'pending',
            $data['notes'] ?? null
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Sipariş ürünlerini ekle
        foreach ($data['items'] as $item) {
            // Ürün bilgilerini tekrar getir
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch();
            
            // Sipariş öğesi ekle
            $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                                    VALUES (?, ?, ?, ?, ?, ?)');
            
            $stmt->execute([
                $order_id,
                $product['id'],
                $product['name'],
                $product['price'],
                $item['quantity'],
                $product['price'] * $item['quantity']
            ]);
            
            // Stok güncelle
            $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            $stmt->execute([$item['quantity'], $product['id']]);
        }
        
        $pdo->commit();
        
        // Başarılı cevap
        http_response_code(201); // Created
        $response['success'] = true;
        $response['message'] = 'Sipariş başarıyla oluşturuldu.';
        $response['data'] = [
            'order_id' => $order_id,
            'total' => $total
        ];
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
    
    echo json_encode($response);
}

/**
 * Sipariş güncelle (admin)
 */
function updateOrder($id) {
    global $pdo, $response;
    
    try {
        // Sipariş kontrolü
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            http_response_code(404);
            $response['message'] = 'Sipariş bulunamadı.';
            echo json_encode($response);
            return;
        }
        
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Güncellenecek alanları hazırla
        $fields = [];
        $params = [];
        
        // Durum
        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $params[] = $data['status'];
        }
        
        // Notlar
        if (isset($data['notes'])) {
            $fields[] = 'notes = ?';
            $params[] = $data['notes'];
        }
        
        // Güncelleme tarihi
        $fields[] = 'updated_at = NOW()';
        
        // Güncelleme sorgusu
        if (!empty($fields)) {
            $sql = 'UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                // Başarılı cevap
                http_response_code(200);
                $response['success'] = true;
                $response['message'] = 'Sipariş başarıyla güncellendi.';
                $response['data'] = ['id' => $id];
            } else {
                // Hata durumu
                http_response_code(500);
                $response['message'] = 'Sipariş güncellenirken bir hata oluştu.';
            }
        } else {
            // Güncellenecek alan yok
            http_response_code(400);
            $response['message'] = 'Güncellenecek veri bulunamadı.';
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
    
    echo json_encode($response);
}