<?php
require_once 'config.php';
require_admin();

// Ürün ID kontrolü
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_new = ($id == 0);
$product = [];
$categories = [];

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $name = isset($_POST['name']) ? clean_input($_POST['name']) : '';
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $description = isset($_POST['description']) ? clean_input($_POST['description']) : '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $original_price = isset($_POST['original_price']) ? floatval($_POST['original_price']) : 0;
    $discount_percent = isset($_POST['discount_percent']) ? floatval($_POST['discount_percent']) : 0;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $active = isset($_POST['active']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Veri doğrulama
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Ürün adı boş olamaz.';
    }
    
    if ($category_id <= 0) {
        $errors[] = 'Lütfen bir kategori seçin.';
    }
    
    if ($price <= 0) {
        $errors[] = 'Ürün fiyatı 0\'dan büyük olmalıdır.';
    }
    
    // Hata yoksa veritabanına kaydet
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Görsel yükleme
            $image_name = '';
            if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
                $image = $_FILES['image'];
                $image_name = time() . '_' . basename($image['name']);
                $target_path = '../uploads/products/' . $image_name;
                
                // Dosya türü kontrolü
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($image['type'], $allowed_types)) {
                    throw new Exception('Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WEBP formatları desteklenmektedir.');
                }
                
                // Dosya boyutu kontrolü (10MB maksimum)
                if ($image['size'] > 10 * 1024 * 1024) {
                    throw new Exception('Dosya boyutu çok büyük. Maksimum 10MB olmalıdır.');
                }
                
                // Dosyayı taşı
                if (!move_uploaded_file($image['tmp_name'], $target_path)) {
                    throw new Exception('Dosya yüklenirken bir hata oluştu.');
                }
            } elseif ($id > 0) {
                // Mevcut ürün için görsel adını koru
                $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $image_name = $stmt->fetchColumn();
            }
            
            // Ürünü kaydet
            if ($id > 0) {
                // Güncelleme
                $sql = 'UPDATE products SET 
                            category_id = ?, 
                            name = ?, 
                            description = ?, 
                            price = ?, 
                            original_price = ?, 
                            discount_percent = ?, 
                            stock = ?, 
                            active = ?, 
                            featured = ?';
                
                $params = [
                    $category_id,
                    $name,
                    $description,
                    $price,
                    $original_price,
                    $discount_percent,
                    $stock,
                    $active,
                    $featured
                ];
                
                if (!empty($image_name)) {
                    $sql .= ', image = ?';
                    $params[] = $image_name;
                }
                
                $sql .= ' WHERE id = ?';
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $success_message = 'Ürün başarıyla güncellendi.';
            } else {
                // Yeni ürün ekleme
                $stmt = $pdo->prepare('INSERT INTO products (category_id, name, description, price, original_price, discount_percent, stock, image, active, featured)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                
                $stmt->execute([
                    $category_id,
                    $name,
                    $description,
                    $price,
                    $original_price,
                    $discount_percent,
                    $stock,
                    $image_name,
                    $active,
                    $featured
                ]);
                
                $id = $pdo->lastInsertId();
                $success_message = 'Ürün başarıyla eklendi.';
            }
            
            $pdo->commit();
            
            // Başarı mesajı
            set_flash_message('success', $success_message);
            redirect('products.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Hata: ' . $e->getMessage();
        }
    }
} else {
    // Ürün bilgilerini getir
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                set_flash_message('danger', 'Ürün bulunamadı.');
                redirect('products.php');
            }
        } catch (PDOException $e) {
            set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
            redirect('products.php');
        }
    }
}

// Kategorileri getir
try {
    $stmt = $pdo->query('SELECT * FROM categories ORDER BY name');
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Sayfa başlığı
$page_title = $is_new ? 'Yeni Ürün Ekle' : 'Ürün Düzenle';
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $page_title; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="products.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Ürünlere Dön
                    </a>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($id > 0 ? '?id=' . $id : '')); ?>" method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Ürün Adı -->
                                <div class="mb-3">
                                    <label for="name" class="form-label">Ürün Adı</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                                </div>
                                
                                <!-- Kategori -->
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Kategori</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Kategori Seçin</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo (isset($product['category_id']) && $product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Açıklama -->
                                <div class="mb-3">
                                    <label for="description" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Fiyat Bilgileri -->
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="price" class="form-label">Fiyat (TL)</label>
                                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo isset($product['price']) ? number_format($product['price'], 2, '.', '') : ''; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="original_price" class="form-label">Normal Fiyat (TL) <small class="text-muted">(opsiyonel)</small></label>
                                        <input type="number" class="form-control" id="original_price" name="original_price" step="0.01" min="0" value="<?php echo isset($product['original_price']) ? number_format($product['original_price'], 2, '.', '') : ''; ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="discount_percent" class="form-label">İndirim Oranı (%)</label>
                                        <input type="number" class="form-control" id="discount_percent" name="discount_percent" step="0.1" min="0" max="100" value="<?php echo $product['discount_percent'] ?? 0; ?>">
                                    </div>
                                </div>
                                
                                <!-- Stok -->
                                <div class="mb-3">
                                    <label for="stock" class="form-label">Stok Miktarı</label>
                                    <input type="number" class="form-control" id="stock" name="stock" min="0" value="<?php echo $product['stock'] ?? 0; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Görsel -->
                                <div class="mb-3">
                                    <label for="image" class="form-label">Ürün Görseli</label>
                                    <?php if (!empty($product['image'])): ?>
                                        <div class="mb-2">
                                            <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-thumbnail" style="max-height: 200px;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <div class="form-text">Maksimum dosya boyutu: 10MB. Desteklenen formatlar: JPG, PNG, GIF, WEBP.</div>
                                </div>
                                
                                <!-- Durum ve Öne Çıkarma -->
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="active" name="active" <?php echo (!isset($product['active']) || $product['active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="active">Aktif</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="featured" name="featured" <?php echo (isset($product['featured']) && $product['featured']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="featured">Öne Çıkan Ürün</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="products.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times"></i> İptal
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>

<script>
// İndirim oranı hesaplama
document.addEventListener('DOMContentLoaded', function() {
    const priceInput = document.getElementById('price');
    const originalPriceInput = document.getElementById('original_price');
    const discountPercentInput = document.getElementById('discount_percent');
    
    // Fiyat veya normal fiyat değiştiğinde indirim oranını hesapla
    function updateDiscountPercent() {
        const price = parseFloat(priceInput.value) || 0;
        const originalPrice = parseFloat(originalPriceInput.value) || 0;
        
        if (originalPrice > 0 && price > 0 && originalPrice > price) {
            const discountPercent = ((originalPrice - price) / originalPrice) * 100;
            discountPercentInput.value = discountPercent.toFixed(1);
        }
    }
    
    // İndirim oranı değiştiğinde normal fiyatı hesapla
    function updateOriginalPrice() {
        const price = parseFloat(priceInput.value) || 0;
        const discountPercent = parseFloat(discountPercentInput.value) || 0;
        
        if (price > 0 && discountPercent > 0) {
            const originalPrice = price / (1 - (discountPercent / 100));
            originalPriceInput.value = originalPrice.toFixed(2);
        }
    }
    
    // Olay dinleyicileri
    priceInput.addEventListener('input', updateDiscountPercent);
    originalPriceInput.addEventListener('input', updateDiscountPercent);
    discountPercentInput.addEventListener('input', updateOriginalPrice);
});
</script>