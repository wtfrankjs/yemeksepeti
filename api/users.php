<?php
/**
 * Kullanıcılar API - RESTful API Endpoint
 * 
 * HTTP Methodları:
 * GET /api/users - Tüm kullanıcıları listele (admin)
 * GET /api/users/123 - ID'si 123 olan kullanıcıyı getir (admin veya kendisi)
 * POST /api/users - Yeni kullanıcı ekle (kayıt)
 * PUT /api/users/123 - ID'si 123 olan kullanıcıyı güncelle (admin veya kendisi)
 * DELETE /api/users/123 - ID'si 123 olan kullanıcıyı sil (admin)
 */

// Config dosyasını include et
require_once '../config/config.php';

// CORS ayarları (Cross-Origin Resource Sharing)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// HTTP methodunu al
$request_method = $_SERVER["REQUEST_METHOD"];

// Kullanıcı ID'si varsa al
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
            // Tek kullanıcı getir
            getUser($id);
        } else {
            // Tüm kullanıcıları listele (admin)
            getUsers();
        }
        break;
    
    case 'POST':
        // Yeni kullanıcı ekle (kayıt)
        addUser();
        break;
    
    case 'PUT':
        // Kullanıcı güncelle (admin veya kendisi)
        if ($id > 0) {
            updateUser($id);
        } else {
            http_response_code(400); // Bad Request
            $response['message'] = 'Kullanıcı ID\'si belirtilmelidir.';
            echo json_encode($response);
        }
        break;
    
    case 'DELETE':
        // Kullanıcı sil (admin)
        if ($id > 0 && is_admin()) {
            deleteUser($id);
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
 * Tüm kullanıcıları getir (Admin)
 */
function getUsers() {
    global $pdo, $response;
    
    // Yetki kontrolü
    if (!is_admin()) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Bu işlem için yetkiniz bulunmamaktadır.';
        echo json_encode($response);
        return;
    }
    
    try {
        // Sorgu parametreleri
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        
        // Sorgu oluşturma
        $sql = 'SELECT id, first_name, last_name, email, phone, is_admin, created_at, updated_at FROM users WHERE 1=1';
        $params = [];
        
        // Arama filtresi
        if (!empty($search)) {
            $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Sıralama
        $sql .= ' ORDER BY id DESC';
        
        // Limitleme
        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
            
            if ($offset > 0) {
                $sql .= ' OFFSET ?';
                $params[] = $offset;
            }
        }
        
        // Kullanıcıları getir
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        // Toplam kullanıcı sayısı
        $count_sql = str_replace('SELECT id, first_name, last_name, email, phone, is_admin, created_at, updated_at', 'SELECT COUNT(*) as total', $sql);
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
        $response['message'] = 'Kullanıcılar başarıyla getirildi.';
        $response['data'] = [
            'total' => $total,
            'items' => $users
        ];
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Tek kullanıcı getir
 */
function getUser($id) {
    global $pdo, $response;
    
    // Yetki kontrolü
    $current_user_id = is_logged_in() ? $_SESSION['user_id'] : 0;
    
    if (!is_admin() && $current_user_id != $id) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Bu işlem için yetkiniz bulunmamaktadır.';
        echo json_encode($response);
        return;
    }
    
    try {
        // Kullanıcı bilgilerini getir (şifre hariç)
        $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, is_admin, created_at, updated_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // İsteğe bağlı olarak kullanıcının adreslerini de getir
            if (isset($_GET['include_addresses']) && $_GET['include_addresses'] == 'true') {
                $stmt = $pdo->prepare('SELECT * FROM addresses WHERE user_id = ?');
                $stmt->execute([$id]);
                $user['addresses'] = $stmt->fetchAll();
            }
            
            // İsteğe bağlı olarak kullanıcının siparişlerini de getir
            if (isset($_GET['include_orders']) && $_GET['include_orders'] == 'true') {
                $stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
                $stmt->execute([$id]);
                $user['orders'] = $stmt->fetchAll();
            }
            
            // Başarılı cevap
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Kullanıcı başarıyla getirildi.';
            $response['data'] = $user;
        } else {
            // Kullanıcı bulunamadı
            http_response_code(404);
            $response['message'] = 'Kullanıcı bulunamadı.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Yeni kullanıcı ekle (kayıt)
 */
function addUser() {
    global $pdo, $response;
    
    try {
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Gerekli alanları kontrol et
        if (!isset($data['first_name']) || !isset($data['last_name']) || !isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            $response['message'] = 'Eksik veri. Ad, soyad, e-posta ve şifre zorunludur.';
            echo json_encode($response);
            return;
        }
        
        // E-posta formatı kontrolü
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            $response['message'] = 'Geçerli bir e-posta adresi giriniz.';
            echo json_encode($response);
            return;
        }
        
        // Şifre uzunluğu kontrolü
        if (strlen($data['password']) < 6) {
            http_response_code(400);
            $response['message'] = 'Şifre en az 6 karakter olmalıdır.';
            echo json_encode($response);
            return;
        }
        
        // E-posta adresi kullanımda mı?
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        
        if ($stmt->fetchColumn() > 0) {
            http_response_code(400);
            $response['message'] = 'Bu e-posta adresi zaten kullanımda.';
            echo json_encode($response);
            return;
        }
        
        // Şifre hash'leme
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Kullanıcı ekleme
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password, is_admin) VALUES (?, ?, ?, ?, ?, ?)');
        
        $result = $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'] ?? null,
            $hashed_password,
            isset($data['is_admin']) && is_admin() ? $data['is_admin'] : 0 // Admin oluşturabilmek için admin yetkisi gerekli
        ]);
        
        if ($result) {
            // Başarılı cevap
            $id = $pdo->lastInsertId();
            
            http_response_code(201); // Created
            $response['success'] = true;
            $response['message'] = 'Kullanıcı başarıyla oluşturuldu.';
            $response['data'] = [
                'id' => $id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email']
            ];
        } else {
            // Hata durumu
            http_response_code(500);
            $response['message'] = 'Kullanıcı oluşturulurken bir hata oluştu.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Kullanıcı güncelle
 */
function updateUser($id) {
    global $pdo, $response;
    
    // Yetki kontrolü
    $current_user_id = is_logged_in() ? $_SESSION['user_id'] : 0;
    
    if (!is_admin() && $current_user_id != $id) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Bu işlem için yetkiniz bulunmamaktadır.';
        echo json_encode($response);
        return;
    }
    
    try {
        // Kullanıcı kontrolü
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            $response['message'] = 'Kullanıcı bulunamadı.';
            echo json_encode($response);
            return;
        }
        
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Güncellenecek alanları hazırla
        $fields = [];
        $params = [];
        
        // Ad
        if (isset($data['first_name'])) {
            $fields[] = 'first_name = ?';
            $params[] = $data['first_name'];
        }
        
        // Soyad
        if (isset($data['last_name'])) {
            $fields[] = 'last_name = ?';
            $params[] = $data['last_name'];
        }
        
        // E-posta
        if (isset($data['email'])) {
            // E-posta değişiyorsa, kullanımda mı kontrol et
            if ($data['email'] != $user['email']) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
                $stmt->execute([$data['email']]);
                
                if ($stmt->fetchColumn() > 0) {
                    http_response_code(400);
                    $response['message'] = 'Bu e-posta adresi zaten kullanımda.';
                    echo json_encode($response);
                    return;
                }
            }
            
            $fields[] = 'email = ?';
            $params[] = $data['email'];
        }
        
        // Telefon
        if (isset($data['phone'])) {
            $fields[] = 'phone = ?';
            $params[] = $data['phone'];
        }
        
        // Şifre
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                http_response_code(400);
                $response['message'] = 'Şifre en az 6 karakter olmalıdır.';
                echo json_encode($response);
                return;
            }
            
            $fields[] = 'password = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Admin yetkisi (sadece admin değiştirebilir)
        if (isset($data['is_admin']) && is_admin()) {
            $fields[] = 'is_admin = ?';
            $params[] = (int)$data['is_admin'];
        }
        
        // Güncelleme tarihi
        $fields[] = 'updated_at = NOW()';
        
        // Güncelleme sorgusu
        if (!empty($fields)) {
            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                // Başarılı cevap
                http_response_code(200);
                $response['success'] = true;
                $response['message'] = 'Kullanıcı başarıyla güncellendi.';
                $response['data'] = ['id' => $id];
            } else {
                // Hata durumu
                http_response_code(500);
                $response['message'] = 'Kullanıcı güncellenirken bir hata oluştu.';
            }
        } else {
            // Güncellenecek alan yok
            http_response_code(400);
            $response['message'] = 'Güncellenecek veri bulunamadı.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Kullanıcı sil
 */
function deleteUser($id) {
    global $pdo, $response;
    
    // Yetki kontrolü
    if (!is_admin()) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Bu işlem için yetkiniz bulunmamaktadır.';
        echo json_encode($response);
        return;
    }
    
    try {
        // Kullanıcı kontrolü
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            $response['message'] = 'Kullanıcı bulunamadı.';
            echo json_encode($response);
            return;
        }
        
        // Son admin mi kontrolü
        if ($user['is_admin']) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE is_admin = 1');
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount <= 1) {
                http_response_code(400);
                $response['message'] = 'Son admin kullanıcı silinemez.';
                echo json_encode($response);
                return;
            }
        }
        
        // İşlem başlat
        $pdo->beginTransaction();
        
        // Adresleri sil
        $stmt = $pdo->prepare('DELETE FROM addresses WHERE user_id = ?');
        $stmt->execute([$id]);
        
        // Favorileri sil
        $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ?');
        $stmt->execute([$id]);
        
        // Kullanıcıyı sil
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $result = $stmt->execute([$id]);
        
        if ($result) {
            $pdo->commit();
            
            // Başarılı cevap
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Kullanıcı başarıyla silindi.';
        } else {
            $pdo->rollBack();
            
            // Hata durumu
            http_response_code(500);
            $response['message'] = 'Kullanıcı silinirken bir hata oluştu.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}