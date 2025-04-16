<?php
/**
 * Yemeksepeti API - Ana Endpoint
 * Bu dosya API dokümantasyonu ve kullanılabilir endpoint'leri listeler
 */

// Config dosyasını include et
require_once '../config/config.php';

// CORS ayarları
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// API Bilgisi
$api_info = [
    'name' => 'Yemeksepeti API',
    'version' => '1.0.0',
    'description' => 'Yemeksepeti clone uygulaması için RESTful API',
    'base_url' => SITE_URL . '/api',
    'endpoints' => [
        [
            'path' => '/products',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'description' => 'Ürün işlemleri',
            'params' => [
                'GET' => ['category', 'search', 'limit', 'offset'],
                'POST' => ['name', 'category_id', 'price', 'description', 'original_price', 'discount_percent', 'stock', 'image', 'active'],
                'PUT' => ['name', 'category_id', 'price', 'description', 'original_price', 'discount_percent', 'stock', 'image', 'active'],
                'DELETE' => []
            ]
        ],
        [
            'path' => '/categories',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'description' => 'Kategori işlemleri',
            'params' => [
                'GET' => ['search', 'limit', 'offset', 'include_products'],
                'POST' => ['name', 'description', 'image'],
                'PUT' => ['name', 'description', 'image'],
                'DELETE' => []
            ]
        ],
        [
            'path' => '/orders',
            'methods' => ['GET', 'POST', 'PUT'],
            'description' => 'Sipariş işlemleri',
            'params' => [
                'GET' => ['status', 'limit', 'offset'],
                'POST' => ['first_name', 'last_name', 'email', 'phone', 'address', 'payment_method', 'items', 'notes'],
                'PUT' => ['status', 'notes']
            ]
        ],
        [
            'path' => '/users',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'description' => 'Kullanıcı işlemleri',
            'params' => [
                'GET' => ['search', 'limit', 'offset', 'include_addresses', 'include_orders'],
                'POST' => ['first_name', 'last_name', 'email', 'phone', 'password'],
                'PUT' => ['first_name', 'last_name', 'email', 'phone', 'password', 'is_admin'],
                'DELETE' => []
            ]
        ]
    ],
    'auth' => [
        'description' => 'Bazı işlemler için yetki gerekir. Oturum açılmış olmalıdır.'
    ]
];

// API bilgilerini JSON olarak döndür
echo json_encode($api_info, JSON_PRETTY_PRINT);