<?php
/**
 * Ürünler API - RESTful API Endpoint
 * 
 * HTTP Methodları:
 * GET /api/products - Tüm ürünleri listele
 * GET /api/products/123 - ID'si 123 olan ürünü getir
 * POST /api/products - Yeni ürün ekle (admin)
 * PUT /api/products/123 - ID'si 123 olan ürünü güncelle (admin)
 * DELETE /api/products/123 - ID'si 123 olan ürünü sil (admin)
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

// Ürün ID'si varsa al
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
            // Tek ürün getir
            getProduct($id);
        } else {
            // Tüm ürünleri listele
            getProducts();
        }
        break;
    
    case 'POST':
        // Yeni ürün ekle (admin)
        if (is_admin()) {
            addProduct();
        } else {
            http_response_code(401); // Unauthorized
            $response['message'] = 'Bu işlem için yetkiniz bulunmamaktadır.';
            echo json_encode($response);
        }
        break;
    
    case 'PUT':
        // Ürün güncelle (admin)
        if ($id > 0 && is_admin()) {
            updateProduct($id);
        } else {
            http_response_code(401); // Unauthorized
            $response['message'] = 'Bu işlem için yetkiniz bulunmamaktadır.';
            echo json_encode($response);
        }
        break;
    
    case 'DELETE':
        // Ürün sil (admin)
        if ($id > 0 && is_admin()) {
            deleteProduct($id);
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
 * Tüm ürünleri getir
 */
function getProducts() {
    global $pdo, $response;
    
    try {
        // Filtre parametreleri
        $category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
        $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        // Sorgu oluşturma
        $sql = 'SELECT p.*, c.name as category_name FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.active = 1';
        $params = [];
        
        // Filtreler
        if ($category_id > 0) {
            $sql .= ' AND p.category_id = ?';
            $params[] = $category_id;
        }
        
        if (!empty($search)) {
            $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Sıralama
        $sql .= ' ORDER BY p.id DESC';
        
        // Limitleme
        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
            
            if ($offset > 0) {
                $sql .= ' OFFSET ?';
                $params[] = $offset;
            }
        }
        
        // Ürünleri getir
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Toplam ürün sayısı
        $count_sql = str_replace('p.*, c.name as category_name', 'COUNT(*) as total', $sql);
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
        $response['message'] = 'Ürünler başarıyla getirildi.';
        $response['data'] = [
            'total' => $total,
            'items' => $products
        ];
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Tek ürün getir
 */
function getProduct($id) {
    global $pdo, $response;
    
    try {
        // Ürün bilgilerini getir
        $stmt = $pdo->prepare('SELECT p.*, c.name as category_name FROM products p
                               LEFT JOIN categories c ON p.category_id = c.id
                               WHERE p.id = ? AND p.active = 1');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Başarılı cevap
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Ürün başarıyla getirildi.';
            $response['data'] = $product;
        } else {
            // Ürün bulunamadı
            http_response_code(404);
            $response['message'] = 'Ürün bulunamadı.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Yeni ürün ekle
 */
function addProduct() {
    global $pdo, $response;
    
    try {
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Gerekli alanları kontrol et
        if (!isset($data['name']) || !isset($data['category_id']) || !isset($data['price'])) {
            http_response_code(400);
            $response['message'] = 'Eksik veri. Ürün adı, kategori ve fiyat zorunludur.';
            echo json_encode($response);
            return;
        }
        
        // Ürün ekle
        $stmt = $pdo->prepare('INSERT INTO products (category_id, name, description, price, original_price, discount_percent, stock, image, active)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        
        $result = $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['price'],
            $data['original_price'] ?? null,
            $data['discount_percent'] ?? null,
            $data['stock'] ?? 0,
            $data['image'] ?? null,
            $data['active'] ?? 1
        ]);
        
        if ($result) {
            // Başarılı cevap
            $id = $pdo->lastInsertId();
            
            http_response_code(201); // Created
            $response['success'] = true;
            $response['message'] = 'Ürün başarıyla eklendi.';
            $response['data'] = ['id' => $id];
        } else {
            // Hata durumu
            http_response_code(500);
            $response['message'] = 'Ürün eklenirken bir hata oluştu.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Ürün güncelle
 */
function updateProduct($id) {
    global $pdo, $response;
    
    try {
        // Ürün kontrolü
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            http_response_code(404);
            $response['message'] = 'Ürün bulunamadı.';
            echo json_encode($response);
            return;
        }
        
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Güncellenecek alanları hazırla
        $fields = [];
        $params = [];
        
        // Kategori ID
        if (isset($data['category_id'])) {
            $fields[] = 'category_id = ?';
            $params[] = $data['category_id'];
        }
        
        // Ürün adı
        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = $data['name'];
        }
        
        // Açıklama
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $params[] = $data['description'];
        }
        
        // Fiyat
        if (isset($data['price'])) {
            $fields[] = 'price = ?';
            $params[] = $data['price'];
        }
        
        // Orijinal fiyat
        if (isset($data['original_price'])) {
            $fields[] = 'original_price = ?';
            $params[] = $data['original_price'];
        }
        
        // İndirim yüzdesi
        if (isset($data['discount_percent'])) {
            $fields[] = 'discount_percent = ?';
            $params[] = $data['discount_percent'];
        }
        
        // Stok
        if (isset($data['stock'])) {
            $fields[] = 'stock = ?';
            $params[] = $data['stock'];
        }
        
        // Görsel
        if (isset($data['image'])) {
            $fields[] = 'image = ?';
            $params[] = $data['image'];
        }
        
        // Aktif/Pasif
        if (isset($data['active'])) {
            $fields[] = 'active = ?';
            $params[] = $data['active'];
        }
        
        // Güncelleme tarihi
        $fields[] = 'updated_at = NOW()';
        
        // Güncelleme sorgusu
        if (!empty($fields)) {
            $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                // Başarılı cevap
                http_response_code(200);
                $response['success'] = true;
                $response['message'] = 'Ürün başarıyla güncellendi.';
                $response['data'] = ['id' => $id];
            } else {
                // Hata durumu
                http_response_code(500);
                $response['message'] = 'Ürün güncellenirken bir hata oluştu.';
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
 * Ürün sil
 */
function deleteProduct($id) {
    global $pdo, $response;
    
    try {
        // Ürün kontrolü
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            http_response_code(404);
            $response['message'] = 'Ürün bulunamadı.';
            echo json_encode($response);
            return;
        }
        
        // Ürünü sil
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $result = $stmt->execute([$id]);
        
        if ($result) {
            // Başarılı cevap
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Ürün başarıyla silindi.';
        } else {
            // Hata durumu
            http_response_code(500);
            $response['message'] = 'Ürün silinirken bir hata oluştu.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}