<?php
/**
 * Kimlik Doğrulama API - RESTful API Endpoint
 * 
 * HTTP Methodları:
 * POST /api/auth/login - Giriş yap
 * POST /api/auth/register - Kayıt ol
 * POST /api/auth/logout - Çıkış yap
 * GET /api/auth/user - Mevcut kullanıcı bilgilerini al
 */

// Config dosyasını include et
require_once '../config/config.php';

// CORS ayarları
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// API için cevap hazırla
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// İstek yolunu al
$request_uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
$endpoint = end($uri_parts);

// HTTP methodunu al
$request_method = $_SERVER["REQUEST_METHOD"];

// İstek yoluna göre işlem yap
switch ($endpoint) {
    case 'login':
        if ($request_method === 'POST') {
            login();
        } else {
            http_response_code(405); // Method Not Allowed
            $response['message'] = 'Bu endpoint sadece POST isteği kabul eder.';
            echo json_encode($response);
        }
        break;
    
    case 'register':
        if ($request_method === 'POST') {
            register();
        } else {
            http_response_code(405); // Method Not Allowed
            $response['message'] = 'Bu endpoint sadece POST isteği kabul eder.';
            echo json_encode($response);
        }
        break;
    
    case 'logout':
        if ($request_method === 'POST') {
            logout();
        } else {
            http_response_code(405); // Method Not Allowed
            $response['message'] = 'Bu endpoint sadece POST isteği kabul eder.';
            echo json_encode($response);
        }
        break;
    
    case 'user':
        if ($request_method === 'GET') {
            getUser();
        } else {
            http_response_code(405); // Method Not Allowed
            $response['message'] = 'Bu endpoint sadece GET isteği kabul eder.';
            echo json_encode($response);
        }
        break;
    
    default:
        http_response_code(404); // Not Found
        $response['message'] = 'Geçersiz endpoint.';
        echo json_encode($response);
        break;
}

/**
 * Giriş yap
 */
function login() {
    global $pdo, $response;
    
    try {
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Gerekli alanları kontrol et
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            $response['message'] = 'E-posta ve şifre zorunludur.';
            echo json_encode($response);
            return;
        }
        
        $email = $data['email'];
        $password = $data['password'];
        $remember = isset($data['remember']) ? (bool)$data['remember'] : false;
        
        // Kullanıcıyı bul
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Kullanıcı bulundu ve şifre doğru
        if ($user && password_verify($password, $user['password'])) {
            // Oturum oluştur
            $_SESSION['user_id'] = $user['id'];
            
            // Admin kontrolü
            if ($user['is_admin']) {
                $_SESSION['is_admin'] = true;
            }
            
            // Beni hatırla
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60); // 30 gün
                
                // Token kaydı
                $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
                $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', $expires)]);
                
                // Cookie
                setcookie('remember_token', $token, $expires, '/', '', false, true);
            }
            
            // Kullanıcı bilgilerini hazırla (şifre hariç)
            $user_data = [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'is_admin' => (bool)$user['is_admin']
            ];
            
            // Başarılı cevap
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Giriş başarılı.';
            $response['data'] = $user_data;
        } else {
            // Hatalı kimlik bilgileri
            http_response_code(401);
            $response['message'] = 'E-posta adresi veya şifre hatalı.';
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
    
    echo json_encode($response);
}

/**
 * Kayıt ol
 */
function register() {
    global $pdo, $response;
    
    try {
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Gerekli alanları kontrol et
        if (!isset($data['first_name']) || !isset($data['last_name']) || !isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            $response['message'] = 'Ad, soyad, e-posta ve şifre zorunludur.';
            echo json_encode($response);
            return;
        }
        
        $first_name = $data['first_name'];
        $last_name = $data['last_name'];
        $email = $data['email'];
        $phone = $data['phone'] ?? null;
        $password = $data['password'];
        $password_confirm = $data['password_confirm'] ?? $password; // İsteğe bağlı kontrol
        
        // E-posta formatı kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            $response['message'] = 'Geçerli bir e-posta adresi giriniz.';
            echo json_encode($response);
            return;
        }
        
        // Şifre eşleşme kontrolü
        if ($password !== $password_confirm) {
            http_response_code(400);
            $response['message'] = 'Şifreler eşleşmiyor.';
            echo json_encode($response);
            return;
        }
        
        // Şifre uzunluğu kontrolü
        if (strlen($password) < 6) {
            http_response_code(400);
            $response['message'] = 'Şifre en az 6 karakter olmalıdır.';
            echo json_encode($response);
            return;
        }
        
        // E-posta adresi kullanımda mı?
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        
        if ($stmt->fetchColumn() > 0) {
            http_response_code(400);
            $response['message'] = 'Bu e-posta adresi zaten kullanımda.';
            echo json_encode($response);
            return;
        }
        
        // Şifre hash'leme
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Kullanıcı kayıt
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password) VALUES (?, ?, ?, ?, ?)');
        $result = $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password]);
        
        if ($result) {
            $user_id = $pdo->lastInsertId();
            
            // Otomatik giriş yap
            $_SESSION['user_id'] = $user_id;
            
            // Kullanıcı bilgilerini hazırla
            $user_data = [
                'id' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'is_admin' => false
            ];
            
            // Başarılı cevap
            http_response_code(201); // Created
            $response['success'] = true;
            $response['message'] = 'Kayıt başarılı.';
            $response['data'] = $user_data;
        } else {
            // Hata durumu
            http_response_code(500);
            $response['message'] = 'Kayıt işlemi sırasında bir hata oluştu.';
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
    
    echo json_encode($response);
}

/**
 * Çıkış yap
 */
function logout() {
    global $response;
    
    // Oturumu sonlandır
    session_unset();
    session_destroy();
    
    // Hatırla token'ını sil
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Başarılı cevap
    http_response_code(200);
    $response['success'] = true;
    $response['message'] = 'Çıkış başarılı.';
    
    echo json_encode($response);
}

/**
 * Mevcut kullanıcı bilgilerini al
 */
function getUser() {
    global $pdo, $response;
    
    if (!is_logged_in()) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Oturum açılmamış.';
        echo json_encode($response);
        return;
    }
    
    try {
        $user_id = $_SESSION['user_id'];
        
        // Kullanıcı bilgilerini getir (şifre hariç)
        $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, is_admin, created_at, updated_at FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // İsteğe bağlı olarak kullanıcının adreslerini de getir
            if (isset($_GET['include_addresses']) && $_GET['include_addresses'] == 'true') {
                $stmt = $pdo->prepare('SELECT * FROM addresses WHERE user_id = ?');
                $stmt->execute([$user_id]);
                $user['addresses'] = $stmt->fetchAll();
            }
            
            // Başarılı cevap
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Kullanıcı bilgileri başarıyla getirildi.';
            $response['data'] = $user;
        } else {
            // Kullanıcı bulunamadı (oturum geçersiz)
            session_unset();
            session_destroy();
            
            http_response_code(401); // Unauthorized
            $response['message'] = 'Geçersiz oturum.';
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
    
    echo json_encode($response);
}