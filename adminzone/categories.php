<?php
require_once 'config.php';
require_admin();

// Kategori ID kontrolü
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_new = ($id == 0);
$category = [];
$parent_categories = [];

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $name = isset($_POST['name']) ? clean_input($_POST['name']) : '';
    $description = isset($_POST['description']) ? clean_input($_POST['description']) : '';
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
    $active = isset($_POST['active']) ? 1 : 0;
    
    if ($parent_id === 0) {
        $parent_id = null; // Ana kategori
    }
    
    // Veri doğrulama
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Kategori adı boş olamaz.';
    }
    
    // Yeni kategori için benzersizlik kontrolü
    if ($is_new || ($id > 0 && $name !== $category['name'])) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE name = ?');
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Bu isimde bir kategori zaten mevcut.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
    
    // Döngüsel alt kategori kontrolü (kendi kendinin alt kategorisi olamaz)
    if ($id > 0 && $parent_id == $id) {
        $errors[] = 'Bir kategori kendisinin alt kategorisi olamaz.';
    }
    
    // Hata yoksa veritabanına kaydet
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Görsel yükleme
            $image_name = '';
            if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
                $image = $_FILES['image'];
                $image_name = 'category_' . time() . '_' . basename($image['name']);
                $target_path = '../uploads/categories/' . $image_name;
                
                // Dosya türü kontrolü
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($image['type'], $allowed_types)) {
                    throw new Exception('Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WEBP formatları desteklenmektedir.');
                }
                
                // Dosya boyutu kontrolü (5MB maksimum)
                if ($image['size'] > 5 * 1024 * 1024) {
                    throw new Exception('Dosya boyutu çok büyük. Maksimum 5MB olmalıdır.');
                }
                
                // Dosyayı taşı
                if (!move_uploaded_file($image['tmp_name'], $target_path)) {
                    throw new Exception('Dosya yüklenirken bir hata oluştu.');
                }
            } elseif ($id > 0) {
                // Mevcut kategori için görsel adını koru
                $stmt = $pdo->prepare('SELECT image FROM categories WHERE id = ?');
                $stmt->execute([$id]);
                $image_name = $stmt->fetchColumn();
            }
            
            // Kategoriyi kaydet
            if ($id > 0) {
                // Güncelleme
                $sql = 'UPDATE categories SET 
                            name = ?, 
                            description = ?, 
                            parent_id = ?, 
                            sort_order = ?, 
                            active = ?';
                
                $params = [
                    $name,
                    $description,
                    $parent_id,
                    $sort_order,
                    $active
                ];
                
                if (!empty($image_name)) {
                    $sql .= ', image = ?';
                    $params[] = $image_name;
                }
                
                $sql .= ' WHERE id = ?';
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $success_message = 'Kategori başarıyla güncellendi.';
            } else {
                // Yeni kategori ekleme
                $stmt = $pdo->prepare('INSERT INTO categories (name, description, parent_id, sort_order, image, active)
                                      VALUES (?, ?, ?, ?, ?, ?)');
                
                $stmt->execute([
                    $name,
                    $description,
                    $parent_id,
                    $sort_order,
                    $image_name,
                    $active
                ]);
                
                $id = $pdo->lastInsertId();
                $success_message = 'Kategori başarıyla eklendi.';
            }
            
            $pdo->commit();
            
            // Başarı mesajı
            set_flash_message('success', $success_message);
            redirect('categories.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Hata: ' . $e->getMessage();
        }
    }
} else {
    // Kategori bilgilerini getir
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $category = $stmt->fetch();
            
            if (!$category) {
                set_flash_message('danger', 'Kategori bulunamadı.');
                redirect('categories.php');
            }
        } catch (PDOException $e) {
            set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
            redirect('categories.php');
        }
    }
}

// Üst kategorileri getir (kendi hariç)
try {
    $sql = 'SELECT * FROM categories WHERE id != ? ORDER BY name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id ?: 0]);
    $parent_categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $parent_categories = [];
}

// Sayfa başlığı
$page_title = $is_new ? 'Yeni Kategori Ekle' : 'Kategori Düzenle';
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $page_title; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="categories.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Kategorilere Dön
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
                                <!-- Kategori Adı -->
                                <div class="mb-3">
                                    <label for="name" class="form-label">Kategori Adı</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>" required>
                                </div>
                                
                                <!-- Üst Kategori -->
                                <div class="mb-3">
                                    <label for="parent_id" class="form-label">Üst Kategori</label>
                                    <select class="form-select" id="parent_id" name="parent_id">
                                        <option value="0">Ana Kategori (Üst kategorisi yok)</option>
                                        <?php foreach ($parent_categories as $parent): ?>
                                            <option value="<?php echo $parent['id']; ?>" <?php echo (isset($category['parent_id']) && $category['parent_id'] == $parent['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($parent['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Açıklama -->
                                <div class="mb-3">
                                    <label for="description" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <!-- Sıralama -->
                                <div class="mb-3">
                                    <label for="sort_order" class="form-label">Sıralama</label>
                                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="<?php echo $category['sort_order'] ?? 0; ?>" min="0">
                                    <div class="form-text">Düşük değerler ilk sırada gösterilir.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Görsel -->
                                <div class="mb-3">
                                    <label for="image" class="form-label">Kategori Görseli</label>
                                    <?php if (!empty($category['image'])): ?>
                                        <div class="mb-2">
                                            <img src="../uploads/categories/<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="img-thumbnail" style="max-height: 200px;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <div class="form-text">Maksimum dosya boyutu: 5MB. Desteklenen formatlar: JPG, PNG, GIF, WEBP.</div>
                                </div>
                                
                                <!-- Durum -->
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="active" name="active" <?php echo (!isset($category['active']) || $category['active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="active">Aktif</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="categories.php" class="btn btn-secondary me-md-2">
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