<?php
/**
 * Kategoriler API - RESTful API Endpoint
 * 
 * HTTP Methodları:
 * GET /api/categories - Tüm kategorileri listele
 * GET /api/categories/123 - ID'si 123 olan kategoriyi getir
 * POST /api/categories - Yeni kategori ekle (admin)
 * PUT /api/categories/123 - ID'si 123 olan kategoriyi güncelle (admin)
 * DELETE /api/categories/123 - ID'si 123 olan kategoriyi sil (admin)
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

// Kategori ID'si varsa al
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
            // Tek kategori getir
            getCategory($id);
        } else {
            // Tüm kategorileri listele
            getCategories();
        }
        break;
    
    case 'POST':
        // Yeni kategori ekle (admin)
        if (is_admin()) {
            addCategory();
        } else {
            http_response_code(401); // Unauthorized
            $response['message'] = 'Bu işlem için yetkiniz bulunmamaktadır.';
            echo json_encode($response);
        }
        break;
    
    case 'PUT':
        // Kategori güncelle (admin)
        if ($id > 0 && is_admin()) {
            updateCategory($id);
        } else {
            http_response_code(401); // Unauthorized
            $response['message'] = 'Bu işlem için yetkiniz bulunmamaktadır.';
            echo json_encode($response);
        }
        break;
    
    case 'DELETE':
        // Kategori sil (admin)
        if ($id > 0 && is_admin()) {
            deleteCategory($id);
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
 * Tüm kategorileri getir
 */
function getCategories() {
    global $pdo, $response;
    
    try {
        // Sorgu parametreleri
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        
        // Sorgu oluşturma
        $sql = 'SELECT * FROM categories WHERE 1=1';
        $params = [];
        
        // Arama filtresi
        if (!empty($search)) {
            $sql .= ' AND name LIKE ?';
            $params[] = "%$search%";
        }
        
        // Sıralama
        $sql .= ' ORDER BY name ASC';
        
        // Limitleme
        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
            
            if ($offset > 0) {
                $sql .= ' OFFSET ?';
                $params[] = $offset;
            }
        }
        
        // Kategorileri getir
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll();
        
        // Her kategori için ürün sayısını getir
        foreach ($categories as &$category) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $stmt->execute([$category['id']]);
            $category['product_count'] = (int)$stmt->fetchColumn();
        }
        
        // Toplam kategori sayısı
        $count_sql = str_replace('SELECT *', 'SELECT COUNT(*) as total', $sql);
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
        $response['message'] = 'Kategoriler başarıyla getirildi.';
        $response['data'] = [
            'total' => $total,
            'items' => $categories
        ];
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Tek kategori getir
 */
function getCategory($id) {
    global $pdo, $response;
    
    try {
        // Kategori bilgilerini getir
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if ($category) {
            // Kategoriye ait ürün sayısı
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $stmt->execute([$id]);
            $category['product_count'] = (int)$stmt->fetchColumn();
            
            // İsteğe bağlı olarak kategoriye ait ürünleri de getir
            if (isset($_GET['include_products']) && $_GET['include_products'] == 'true') {
                $stmt = $pdo->prepare('SELECT * FROM products WHERE category_id = ? AND active = 1 ORDER BY name');
                $stmt->execute([$id]);
                $category['products'] = $stmt->fetchAll();
            }
            
            // Başarılı cevap
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Kategori başarıyla getirildi.';
            $response['data'] = $category;
        } else {
            // Kategori bulunamadı
            http_response_code(404);
            $response['message'] = 'Kategori bulunamadı.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Yeni kategori ekle
 */
function addCategory() {
    global $pdo, $response;
    
    try {
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Gerekli alanları kontrol et
        if (!isset($data['name'])) {
            http_response_code(400);
            $response['message'] = 'Eksik veri. Kategori adı zorunludur.';
            echo json_encode($response);
            return;
        }
        
        // Kategori ekle
        $stmt = $pdo->prepare('INSERT INTO categories (name, description, image) VALUES (?, ?, ?)');
        
        $result = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['image'] ?? null
        ]);
        
        if ($result) {
            // Başarılı cevap
            $id = $pdo->lastInsertId();
            
            http_response_code(201); // Created
            $response['success'] = true;
            $response['message'] = 'Kategori başarıyla eklendi.';
            $response['data'] = ['id' => $id];
        } else {
            // Hata durumu
            http_response_code(500);
            $response['message'] = 'Kategori eklenirken bir hata oluştu.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}

/**
 * Kategori güncelle
 */
function updateCategory($id) {
    global $pdo, $response;
    
    try {
        // Kategori kontrolü
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            http_response_code(404);
            $response['message'] = 'Kategori bulunamadı.';
            echo json_encode($response);
            return;
        }
        
        // JSON verisini al
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Güncellenecek alanları hazırla
        $fields = [];
        $params = [];
        
        // Kategori adı
        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = $data['name'];
        }
        
        // Açıklama
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $params[] = $data['description'];
        }
        
        // Görsel
        if (isset($data['image'])) {
            $fields[] = 'image = ?';
            $params[] = $data['image'];
        }
        
        // Güncelleme tarihi
        $fields[] = 'updated_at = NOW()';
        
        // Güncelleme sorgusu
        if (!empty($fields)) {
            $sql = 'UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                // Başarılı cevap
                http_response_code(200);
                $response['success'] = true;
                $response['message'] = 'Kategori başarıyla güncellendi.';
                $response['data'] = ['id' => $id];
            } else {
                // Hata durumu
                http_response_code(500);
                $response['message'] = 'Kategori güncellenirken bir hata oluştu.';
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
 * Kategori sil
 */
function deleteCategory($id) {
    global $pdo, $response;
    
    try {
        // Kategori kontrolü
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            http_response_code(404);
            $response['message'] = 'Kategori bulunamadı.';
            echo json_encode($response);
            return;
        }
        
        // Kategoriye ait ürün kontrolü
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$id]);
        $productCount = $stmt->fetchColumn();
        
        if ($productCount > 0) {
            http_response_code(400);
            $response['message'] = 'Bu kategori silinemez çünkü ' . $productCount . ' adet ürün bu kategoriye aittir.';
            echo json_encode($response);
            return;
        }
        
        // Kategoriyi sil
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $result = $stmt->execute([$id]);
        
        if ($result) {
            // Başarılı cevap
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Kategori başarıyla silindi.';
        } else {
            // Hata durumu
            http_response_code(500);
            $response['message'] = 'Kategori silinirken bir hata oluştu.';
        }
        
        echo json_encode($response);
        
    } catch (PDOException $e) {
        http_response_code(500);
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
        echo json_encode($response);
    }
}