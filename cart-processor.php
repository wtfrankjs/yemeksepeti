<?php
require_once 'config/config.php';

// Session'da sepet yoksa oluştur
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [
        'items' => [],       // id => [id, name, price, quantity, image, total]
        'subtotal' => 0,
        'discount' => 0,
        'deliveryFee' => 14.90,
        'total' => 0
    ];
}

$cart =& $_SESSION['cart'];

// Toplamları hesaplayan yardımcı fonksiyon
function calculateCartTotals(&$cart) {
    $cart['subtotal'] = 0;
    foreach ($cart['items'] as &$item) {
        $item['total'] = $item['price'] * $item['quantity'];
        $cart['subtotal'] += $item['total'];
    }
    $cart['total'] = $cart['subtotal'] + $cart['deliveryFee'] - $cart['discount'];
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'add':
        // Ürün bilgileri
        $id = $_POST['product_id'];
        $name = $_POST['product_name'];
        $price = floatval($_POST['product_price']);
        $img = isset($_POST['product_img']) ? $_POST['product_img'] : 'images/placeholder.jpg';
        
        // Sepette varsa miktarı 1 artır, yoksa ekle
        if (isset($cart['items'][$id])) {
            $cart['items'][$id]['quantity'] += 1;
        } else {
            $cart['items'][$id] = [
                'id' => $id,
                'name' => $name,
                'price' => $price,
                'quantity' => 1,
                'image' => $img,
                'total' => $price
            ];
        }
        calculateCartTotals($cart);
        break;
    
    case 'remove':
        $id = $_POST['product_id'];
        if (isset($cart['items'][$id])) {
            unset($cart['items'][$id]);
        }
        calculateCartTotals($cart);
        break;
    
    case 'updateQuantity':
        $id = $_POST['product_id'];
        $quantity = intval($_POST['quantity']);
        if (isset($cart['items'][$id]) && $quantity > 0) {
            $cart['items'][$id]['quantity'] = $quantity;
        }
        calculateCartTotals($cart);
        break;
    
    case 'clear':
        $cart['items'] = [];
        calculateCartTotals($cart);
        break;
    
    default:
        // action yoksa sepeti aynen döndürebilirsiniz
        break;
}

// JSON olarak döndür
header('Content-Type: application/json; charset=utf-8');
echo json_encode($cart, JSON_UNESCAPED_UNICODE);
exit;
