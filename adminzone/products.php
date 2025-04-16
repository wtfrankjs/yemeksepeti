<?php
require_once 'config.php';
require_admin();

// Silme işlemi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        // Ürün kontrolü
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            set_flash_message('danger', 'Ürün bulunamadı.');
            redirect('admin/products.php');
        }
        
        // Ürünü sil
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        
        set_flash_message('success', 'Ürün başarıyla silindi.');
    } catch (PDOException $e) {
        set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
    }
    
    redirect('admin/products.php');
}

// Filtre parametreleri
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Sayfalama
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Ürünleri al
$products = [];
$total_items = 0;

try {
    // Sorgu oluşturma
    $sql = 'SELECT p.*, c.name as category_name FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE 1=1';
    $params = [];
    
    // Filtreler
    if ($category_id > 0) {
        $sql .= ' AND p.category_id = ?';
        $params[] = $category_id;
    }
    
    if (!empty($search)) {
        $sql .= ' AND p.name LIKE ?';
        $params[] = "%$search%";
    }
    
    // Toplam ürün sayısı
    $count_sql = str_replace('p.*, c.name as category_name', 'COUNT(*) as total', $sql);
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_items = $stmt->fetch()['total'];
    
    // Sayfalama için limit
    $sql .= ' ORDER BY p.id DESC LIMIT ?, ?';
    $params[] = $offset;
    $params[] = $per_page;
    
    // Ürünleri getir
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Kategoriler
    $stmt = $pdo->query('SELECT * FROM categories ORDER BY name');
    $categories = $stmt->fetchAll();
    
} catch (PDOException $e) {
    set_flash_message('danger', 'Veritabanı hatası: ' . $e->getMessage());
}

// Sayfa başlığı
$page_title = 'Ürün Yönetimi';
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Ürün Yönetimi</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="product_add.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-plus"></i> Yeni Ürün Ekle
                    </a>
                </div>
            </div>
            
            <?php echo display_flash_message(); ?>
            
            <!-- Filtreler -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Ürün Ara</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Ürün adı...">
                        </div>
                        <div class="col-md-4">
                            <label for="category" class="form-label">Kategori</label>
                            <select class="form-select" id="category" name="category">
                                <option value="0">Tüm Kategoriler</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filtrele
                            </button>
                            <a href="products.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Sıfırla
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Ürün Listesi -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Görsel</th>
                            <th>Ürün Adı</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th>İndirim</th>
                            <th>Stok</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Ürün bulunamadı.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td>
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" width="50" height="50" class="img-thumbnail">
                                        <?php else: ?>
                                            <div class="text-center text-muted">Görsel Yok</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td><?php echo format_money($product['price']); ?></td>
                                    <td>
                                        <?php if (!empty($product['discount_percent'])): ?>
                                            <span class="badge bg-success">%<?php echo $product['discount_percent']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">Yok</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['stock'] > 0): ?>
                                            <?php echo $product['stock']; ?>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Tükendi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['active']): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="product_edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="products.php?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu ürünü silmek istediğinize emin misiniz?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Sayfalama -->
            <?php
            $total_pages = ceil($total_items / $per_page);
            if ($total_pages > 1):
            ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-chevron-left"></i></span>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-chevron-right"></i></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
