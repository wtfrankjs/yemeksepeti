<?php
/**
 * Veritabanı yapılandırması
 */

// Veritabanı bağlantı bilgileri
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'localhost');
define('DB_NAME', 'yemeksepeti');
define('DB_USER', 'yemeksepeti');
define('DB_PASS', 'yemeksepetidbpassword');
define('DB_CHARSET', 'utf8mb4');

// PDO DSN
define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);

// PDO Seçenekleri
$db_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Veritabanı bağlantısı
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $db_options);
} catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int)$e->getCode());
}

/**
 * Site yapılandırması
 */

// Site URL'si
define('SITE_URL', 'https://yemeksepeti.hizlisiparis.org/yemeksepeti');

// Dosya yolları
define('ROOT_PATH', dirname(__FILE__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Session
session_start();

/**
 * Yardımcı fonksiyonlar
 */

// XSS koruması için input temizleme
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// CSRF Token oluşturma
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token doğrulama
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Para formatı
function format_money($amount) {
    return number_format($amount, 2, ',', '.') . ' TL';
}

// Sayfa yönlendirme
function redirect($page) {
    header('Location: ' . SITE_URL . '/' . $page);
    exit;
}

// Flash mesajlar
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Flash mesajı göster ve temizle
function display_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        
        return '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">
                ' . $flash['message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    return '';
}

// Kullanıcı oturumu kontrolü
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Admin oturumu kontrolü
function is_admin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Kullanıcıyı oturum açmaya yönlendir
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('warning', 'Bu sayfayı görüntülemek için giriş yapmalısınız.');
        redirect('login.php');
    }
}

// Yöneticiyi oturum açmaya yönlendir
function require_admin() {
    if (!is_admin()) {
        set_flash_message('danger', 'Bu sayfaya erişim izniniz bulunmamaktadır.');
        redirect('index.php');
    }
}