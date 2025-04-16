-- Yemeksepeti Clone Database Schema

-- Veritabanını oluştur
CREATE DATABASE IF NOT EXISTS `yemeksepeti` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `yemeksepeti`;

-- Kullanıcılar tablosu
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin kullanıcısı oluştur (şifre: admin123)
INSERT INTO `users` (`email`, `password`, `first_name`, `last_name`, `phone`, `is_admin`) VALUES
('admin@example.com', '$2y$10$K8/YLe94MHbVQW5nD.lkpeJ5iMcO9UJXcbIhfQ92H5KXK/KFrUj7a', 'Admin', 'User', '5551234567', 1);

-- Adresler tablosu
CREATE TABLE `addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `address_line` varchar(255) NOT NULL,
  `address_detail` varchar(255) DEFAULT NULL,
  `address_type` enum('home','work','other') NOT NULL DEFAULT 'home',
  `address_name` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kategoriler tablosu
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kategori verileri
INSERT INTO `categories` (`name`, `description`, `image`) VALUES
('Süt Ürünleri', 'Süt, peynir, yoğurt gibi süt ürünleri', 'category_dairy.jpg'),
('Temel Gıda', 'Un, şeker, tuz, bakliyat gibi temel gıda ürünleri', 'category_basic.jpg'),
('Meyve & Sebze', 'Taze meyve ve sebzeler', 'category_fruits.jpg'),
('İçecekler', 'Su, meyve suyu, gazlı içecekler', 'category_beverages.jpg'),
('Atıştırmalıklar', 'Cips, çikolata, bisküvi gibi atıştırmalıklar', 'category_snacks.jpg');

-- Ürünler tablosu
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `discount_percent` int(11) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ürün verileri
INSERT INTO `products` (`category_id`, `name`, `description`, `price`, `original_price`, `discount_percent`, `stock`, `image`, `active`) VALUES
(1, 'İçim Ayran 1 L', 'Geleneksel içim ayran 1 litre', 31.92, 39.90, 20, 50, 'ayran.jpeg', 1),
(1, 'İçim Taze Peynir 180 g', 'İçim taze beyaz peynir 180g', 41.23, 58.90, 30, 30, 'peynir.jpeg', 1),
(1, 'İçim Yarım Yağlı Tost Peyniri 400 g', 'İçim yarım yağlı tost peyniri 400g', 152.91, 179.90, 15, 25, 'tost_peyniri.jpeg', 1),
(1, 'İçim Süt 1 L', 'İçim tam yağlı günlük süt 1 litre', 27.99, 34.90, 20, 40, 'sut.jpeg', 1),
(1, 'İçim Tereyağı 250 g', 'İçim tereyağı 250g', 89.99, 99.90, 10, 20, 'tereyag.jpeg', 1),
(1, 'İçim Yoğurt 1 kg', 'İçim kaymaklı yoğurt 1 kg', 45.56, 56.95, 20, 35, 'yogurt.jpeg', 1),
(2, 'Barilla Makarna 500 g', 'Barilla spaghetti makarna 500g', 22.45, 29.90, 25, 60, 'makarna.jpeg', 1),
(2, 'Reis Pirinç 1 kg', 'Reis baldo pirinç 1 kg', 64.90, 79.90, 20, 45, 'pirinc.jpeg', 1),
(2, 'Doğuş Şeker 1 kg', 'Doğuş toz şeker 1 kg', 49.90, 59.90, 15, 70, 'seker.jpeg', 1),
(2, 'Yudum Ayçiçek Yağı 1 L', 'Yudum ayçiçek yağı 1 litre', 89.90, 109.90, 18, 40, 'sivi_yag.jpeg', 1),
(2, 'Tat Domates Salçası 700 g', 'Tat domates salçası 700g', 39.90, 49.90, 20, 55, 'salca.jpeg', 1),
(2, 'Söke Un 2 kg', 'Söke genel amaçlı un 2 kg', 34.90, 42.90, 20, 65, 'un.jpeg', 1),
(1, 'President Cheddar'lı Üçgen Peynir 100 g', 'Avrupa'nın peynir ustası President'in sunduğu Cheddar'lı üçgen peynir', 41.56, 48.90, 15, 28, 'peynir.jpeg', 1),
(1, 'Pınar Üçgen Peynir 8'li', 'Pınar üçgen peynir 8 adet', 38.90, NULL, NULL, 40, 'peynir2.jpeg', 1),
(1, 'Pınar Kaşar Peyniri 350g', 'Pınar taze kaşar peyniri 350g', 69.90, 79.90, 12, 30, 'kasar.jpeg', 1),
(1, 'President Labne 200g', 'President labne peynir 200g', 42.50, NULL, NULL, 25, 'labne.jpeg', 1),
(1, 'Tahsildaroğlu Ezine Peyniri 350g', 'Tahsildaroğlu tam yağlı ezine beyaz peynir 350g', 89.90, 109.90, 18, 20, 'ezine.jpeg', 1),
(1, 'Philadelphia Krem Peynir 180g', 'Philadelphia original krem peynir 180g', 54.90, NULL, NULL, 30, 'krem_peynir.jpeg', 1),
(1, 'Elta-Ada Keçi Peyniri 250g', 'Elta-Ada tam yağlı keçi peyniri 250g', 79.90, 89.90, 11, 15, 'keci_peyniri.jpeg', 1);

-- Siparişler tablosu
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `address_detail` text DEFAULT NULL,
  `payment_method` enum('credit_card','cash','pos') NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sipariş detayları tablosu
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kampanyalar tablosu
CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Örnek kampanya verisi
INSERT INTO `campaigns` (`name`, `description`, `code`, `discount_type`, `discount_value`, `min_order_amount`, `start_date`, `end_date`, `active`) VALUES
('Sepette 350 TL İndirim', '350 TL ve üzeri siparişlerde geçerli 50 TL indirim', 'SEPETTE350', 'fixed', 50.00, 350.00, '2023-01-01 00:00:00', '2023-12-31 23:59:59', 1),
('Hoş Geldin İndirimi', 'İlk siparişe özel %20 indirim', 'HOSGELDIN', 'percentage', 20.00, 100.00, '2023-01-01 00:00:00', '2023-12-31 23:59:59', 1);

-- Favoriler tablosu
CREATE TABLE `favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_product` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;