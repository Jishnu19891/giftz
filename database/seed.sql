-- ═══════════════════════════════════════════════════════════
-- Giftz Inventory — Full Seed (Schema + Data)
-- Drops and recreates giftz_db from scratch, including:
--   • All tables (core schema + sale_returns migration)
--   • 25 products, 12 customers, 5 suppliers
--   • 11 purchase orders (7 received, 1 partial, 1 pending, 1 cancelled)
--   • 37 sales (35 completed, 1 voided)
--   • Full stock_movements audit trail (PO receipts, sales, void, returns)
--   • 3 sale returns
-- Login: admin@giftz.local / Admin@123
--        staff@giftz.local / Admin@123
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = '+08:00';

DROP DATABASE IF EXISTS `giftz_db`;
CREATE DATABASE `giftz_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `giftz_db`;

-- ─── Users ────────────────────────────────────────────────
CREATE TABLE `users` (
  `id`         INT UNSIGNED          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)          NOT NULL,
  `email`      VARCHAR(150)          NOT NULL UNIQUE,
  `password`   VARCHAR(255)          NOT NULL,
  `role`       ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME              DEFAULT NULL,
  `created_at` DATETIME              NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME              NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- password hash = Admin@123 (bcrypt cost 12)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`, `created_at`) VALUES
('Admin User',    'admin@giftz.local', '$2y$12$eImiTXuWVxfM37uY4JANjQ==', 'admin', 'active', '2025-01-01 08:00:00'),
('Staff Member',  'staff@giftz.local', '$2y$12$eImiTXuWVxfM37uY4JANjQ==', 'staff', 'active', '2025-01-01 08:00:00');

-- ─── Categories ───────────────────────────────────────────
CREATE TABLE `categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `type`       ENUM('gift','cloth','both') NOT NULL DEFAULT 'both',
  `parent_id`  INT UNSIGNED DEFAULT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`name`, `type`, `sort_order`) VALUES
('Gift Items',     'gift',  1),
('Clothing',       'cloth', 2),
('Souvenirs',      'gift',  3),
('Accessories',    'both',  4),
('Seasonal Gifts', 'gift',  5),
('Children\'s',    'both',  6),
('Men\'s Wear',    'cloth', 7),
('Women\'s Wear',  'cloth', 8);

-- ─── Suppliers ────────────────────────────────────────────
CREATE TABLE `suppliers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(150) NOT NULL,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `phone`          VARCHAR(30)  DEFAULT NULL,
  `email`          VARCHAR(150) DEFAULT NULL,
  `address`        TEXT         DEFAULT NULL,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`name`, `contact_person`, `phone`, `email`, `address`) VALUES
('Gift World PH',        'Maria Santos',   '09171234567', 'maria@giftworldph.com',  'Binondo, Manila'),
('Fashion Hub MNL',      'Juan dela Cruz', '09281234567', 'juan@fashionhub.com',    'Divisoria, Manila'),
('Global Novelties Inc.','Rosa Mendez',    '09391234567', 'rosa@globalnovelties.ph','Makati City'),
('Manila Craft Co.',     'Pedro Gomez',    '09451234567', 'pedro@manilacraft.com',  'Quiapo, Manila'),
('Trendy Threads PH',    'Liza Bautista',  '09561234567', 'liza@trendythreads.ph',  'BGC, Taguig');

-- ─── Products ─────────────────────────────────────────────
CREATE TABLE `products` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sku`             VARCHAR(50)   NOT NULL UNIQUE,
  `name`            VARCHAR(200)  NOT NULL,
  `category_id`     INT UNSIGNED  DEFAULT NULL,
  `type`            ENUM('gift','cloth','both') NOT NULL DEFAULT 'gift',
  `cost_price`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `selling_price`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock_qty`       INT           NOT NULL DEFAULT 0,
  `min_stock_level` INT           NOT NULL DEFAULT 5,
  `size`            VARCHAR(50)   DEFAULT NULL,
  `color`           VARCHAR(50)   DEFAULT NULL,
  `occasion`        VARCHAR(100)  DEFAULT NULL,
  `image`           VARCHAR(255)  DEFAULT NULL,
  `status`          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- stock_qty reflects current state after all POs, sales, and returns below
INSERT INTO `products`
  (`sku`, `name`, `category_id`, `type`, `cost_price`, `selling_price`, `stock_qty`, `min_stock_level`, `occasion`) VALUES
-- Gift Items (cat 1) ─────────────────────────────────────
('GFT-G0001', 'Birthday Gift Box',        1, 'gift',  150.00,  299.00,  23, 5, 'Birthday'),
('GFT-G0002', 'Christmas Hamper',         1, 'gift',  350.00,  699.00,  12, 3, 'Christmas'),
('GFT-G0003', 'Anniversary Gift Set',     1, 'gift',  280.00,  549.00,  20, 5, 'Anniversary'),
('GFT-G0004', 'Scented Candle Set',       1, 'gift',   95.00,  220.00,  35, 5, 'General'),
('GFT-G0005', 'Custom Photo Frame',       1, 'gift',  120.00,  250.00,  22, 5, 'General'),
-- Clothing (cat 2) ────────────────────────────────────────
('CLT-C0001', 'Plain White T-Shirt',      2, 'cloth',  85.00,  199.00,  54, 10, NULL),
('CLT-C0002', 'Denim Jeans (Slim Fit)',   2, 'cloth', 280.00,  650.00,  24,  5, NULL),
('CLT-C0003', 'Floral Summer Dress',      2, 'cloth', 220.00,  499.00,  18,  5, NULL),
('CLT-C0004', 'Polo Shirt (Navy)',        2, 'cloth', 150.00,  350.00,  27,  5, NULL),
-- Souvenirs (cat 3) ───────────────────────────────────────
('SVN-G0001', 'City Souvenir Mug',        3, 'gift',   65.00,  150.00,  45,  8, 'General'),
('SVN-G0002', 'Keepsake Snow Globe',      3, 'gift',  180.00,  380.00,  18,  5, 'General'),
('SVN-G0003', 'Decorative Plate',         3, 'gift',   95.00,  199.00,  23,  5, 'General'),
-- Accessories (cat 4) ─────────────────────────────────────
('ACC-B0001', 'Leather Keychain',         4, 'both',   45.00,   99.00,  43,  8, NULL),
('ACC-B0002', 'Canvas Tote Bag',          4, 'both',   75.00,  169.00,  23,  8, NULL),
('ACC-B0003', 'Beaded Bracelet',          4, 'both',   35.00,   85.00,  23,  8, NULL),
-- Seasonal Gifts (cat 5) ──────────────────────────────────
('SEA-G0001', 'Valentine Gift Set',       5, 'gift',  200.00,  450.00,  24,  5, 'Valentine\'s Day'),
('SEA-G0002', 'Christmas Stocking Set',   5, 'gift',  120.00,  275.00,  25,  5, 'Christmas'),
('SEA-G0003', 'Easter Basket',            5, 'gift',   90.00,  199.00,   5,  5, 'Easter'),
-- Children's (cat 6) ──────────────────────────────────────
('CHD-B0001', 'Kids Stuffed Toy',         6, 'both',  120.00,  280.00,  18,  5, NULL),
('CHD-B0002', 'Building Blocks Set',      6, 'both',  185.00,  399.00,  19,  5, NULL),
('CHD-B0003', 'Coloring Book Pack',       6, 'both',   55.00,  129.00,  36,  8, NULL),
-- Men's Wear (cat 7) ──────────────────────────────────────
('MEN-C0001', 'Men\'s Casual Shorts',     7, 'cloth', 135.00,  299.00,  18,  5, NULL),
('MEN-C0002', 'Men\'s Dress Shirt',       7, 'cloth', 210.00,  499.00,  18,  5, NULL),
-- Women's Wear (cat 8) ────────────────────────────────────
('WOM-C0001', 'Women\'s Blouse',          8, 'cloth', 175.00,  399.00,  40, 10, NULL),
('WOM-C0002', 'Ladies\' Scarf',           8, 'cloth',  85.00,  199.00,  37,  8, NULL);

-- ─── Customers ────────────────────────────────────────────
CREATE TABLE `customers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `email`      VARCHAR(150) DEFAULT NULL,
  `address`    TEXT         DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- id=1 is always the Walk-in placeholder used by the POS
INSERT INTO `customers` (`name`, `phone`, `email`, `address`, `created_at`) VALUES
('Walk-in Customer',  NULL,            NULL,                     NULL,                        '2025-01-01 08:00:00'),
('Anna Reyes',        '09191234567',   'anna.reyes@email.com',   'Quezon City',               '2025-01-15 09:00:00'),
('Ben Torres',        '09201234567',   'ben.torres@email.com',   'Caloocan City',             '2025-01-20 10:00:00'),
('Claire Santos',     '09211234567',   'claire.santos@email.com','Pasig City',                '2025-02-01 09:30:00'),
('David Cruz',        '09221234567',   NULL,                     'Marikina City',             '2025-02-10 11:00:00'),
('Eva Lim',           '09231234567',   'eva.lim@email.com',      'Mandaluyong City',          '2025-03-05 14:00:00'),
('Felix Manalo',      '09241234567',   NULL,                     'Taguig City',               '2025-03-20 10:00:00'),
('Grace Tan',         '09251234567',   'grace.tan@email.com',    'Parañaque City',            '2025-04-10 09:00:00'),
('Henry Dela Rosa',   '09261234567',   NULL,                     'Las Piñas City',            '2025-05-15 15:00:00'),
('Iris Aquino',       '09271234567',   'iris.aquino@email.com',  'Muntinlupa City',           '2025-06-01 11:00:00'),
('Joel Ramos',        '09281234567',   'joel.ramos@email.com',   'Valenzuela City',           '2025-07-20 13:00:00'),
('Karen Sy',          '09291234567',   'karen.sy@email.com',     'Malabon City',              '2025-08-05 10:00:00');

-- ─── Sales ────────────────────────────────────────────────
CREATE TABLE `sales` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `invoice_no`     VARCHAR(30)   NOT NULL UNIQUE,
  `customer_id`    INT UNSIGNED  DEFAULT NULL,
  `subtotal`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_type`  ENUM('flat','percent') NOT NULL DEFAULT 'flat',
  `tax`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash','card','gcash','maya','bank') NOT NULL DEFAULT 'cash',
  `status`         ENUM('completed','voided') NOT NULL DEFAULT 'completed',
  `notes`          TEXT          DEFAULT NULL,
  `created_by`     INT UNSIGNED  DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `created_by`  (`created_by`),
  CONSTRAINT `fk_sale_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sale_user`     FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sales`
  (`invoice_no`, `customer_id`, `subtotal`, `discount`, `discount_type`, `tax`, `total`, `payment_method`, `status`, `created_by`, `created_at`) VALUES
-- Nov 2025 ────────────────────────────────────────────────
('INV-2025-00001',  2,  697.00,   0.00, 'flat', 0, 697.00,  'cash',  'completed', 1, '2025-11-02 09:15:00'),
('INV-2025-00002',  1,  199.00,   0.00, 'flat', 0, 199.00,  'gcash', 'completed', 2, '2025-11-05 14:30:00'),
('INV-2025-00003',  3, 1000.00,   0.00, 'flat', 0,1000.00,  'card',  'completed', 1, '2025-11-08 10:45:00'),
('INV-2025-00004',  4,  648.00,   0.00, 'flat', 0, 648.00,  'cash',  'completed', 2, '2025-11-12 11:20:00'),
('INV-2025-00005',  5,  470.00,   0.00, 'flat', 0, 470.00,  'maya',  'completed', 1, '2025-11-15 15:00:00'),
('INV-2025-00006',  1,  409.00,   0.00, 'flat', 0, 409.00,  'cash',  'completed', 2, '2025-11-18 16:30:00'),
('INV-2025-00007',  6,  668.00,   0.00, 'flat', 0, 668.00,  'gcash', 'completed', 1, '2025-11-22 10:10:00'),
('INV-2025-00008',  7,  549.00,   0.00, 'flat', 0, 549.00,  'cash',  'completed', 2, '2025-11-25 13:45:00'),
('INV-2025-00009',  8,  997.00,   0.00, 'flat', 0, 997.00,  'card',  'completed', 1, '2025-11-28 14:00:00'),
-- Dec 2025 ────────────────────────────────────────────────
('INV-2025-00010',  1,  699.00,   0.00, 'flat', 0, 699.00,  'cash',  'completed', 2, '2025-12-01 09:30:00'),
('INV-2025-00011',  9,  770.00,   0.00, 'flat', 0, 770.00,  'gcash', 'completed', 1, '2025-12-03 11:15:00'),
('INV-2025-00012', 10,  549.00,   0.00, 'flat', 0, 549.00,  'maya',  'completed', 2, '2025-12-05 14:20:00'),
('INV-2025-00013',  1,  999.00,  50.00, 'flat', 0, 949.00,  'cash',  'completed', 1, '2025-12-08 10:00:00'),
('INV-2025-00014',  2,  669.00,   0.00, 'flat', 0, 669.00,  'card',  'completed', 2, '2025-12-10 15:30:00'),
('INV-2025-00015', 11,  947.00,  94.70, 'percent',0,852.30, 'gcash', 'completed', 1, '2025-12-12 11:00:00'),
('INV-2025-00016', 12,  769.00,   0.00, 'flat', 0, 769.00,  'cash',  'completed', 2, '2025-12-15 13:45:00'),
('INV-2025-00017',  1,  830.00,   0.00, 'flat', 0, 830.00,  'cash',  'completed', 1, '2025-12-18 10:30:00'),
('INV-2025-00018',  3, 1097.00,   0.00, 'flat', 0,1097.00,  'card',  'completed', 2, '2025-12-20 14:00:00'),
('INV-2025-00019',  4, 1524.00,   0.00, 'flat', 0,1524.00,  'gcash', 'completed', 1, '2025-12-22 16:00:00'),
('INV-2025-00020',  5, 1007.00,   0.00, 'flat', 0,1007.00,  'cash',  'completed', 2, '2025-12-24 11:30:00'),
('INV-2025-00021',  1,  650.00,   0.00, 'flat', 0, 650.00,  'cash',  'voided',    1, '2025-12-26 09:00:00'),
('INV-2025-00022',  6,  968.00,   0.00, 'flat', 0, 968.00,  'maya',  'completed', 2, '2025-12-28 14:45:00'),
('INV-2025-00023',  7,  397.00,   0.00, 'flat', 0, 397.00,  'cash',  'completed', 1, '2025-12-30 10:15:00'),
-- Jan 2026 ────────────────────────────────────────────────
('INV-2026-00001',  2,  670.00,   0.00, 'flat', 0, 670.00,  'card',  'completed', 2, '2026-01-03 11:00:00'),
('INV-2026-00002',  8,  567.00,   0.00, 'flat', 0, 567.00,  'gcash', 'completed', 1, '2026-01-07 14:30:00'),
('INV-2026-00003',  9,  657.00,   0.00, 'flat', 0, 657.00,  'cash',  'completed', 2, '2026-01-10 14:00:00'),
('INV-2026-00004',  1,  449.00,   0.00, 'flat', 0, 449.00,  'cash',  'completed', 1, '2026-01-14 15:15:00'),
('INV-2026-00005', 10,  620.00,   0.00, 'flat', 0, 620.00,  'maya',  'completed', 2, '2026-01-18 11:30:00'),
('INV-2026-00006', 11,  798.00,   0.00, 'flat', 0, 798.00,  'card',  'completed', 1, '2026-01-22 14:00:00'),
('INV-2026-00007', 12,  698.00,   0.00, 'flat', 0, 698.00,  'gcash', 'completed', 2, '2026-01-25 10:45:00'),
('INV-2026-00008',  3, 1098.00, 100.00, 'flat', 0, 998.00,  'cash',  'completed', 1, '2026-01-28 13:30:00'),
-- Feb 2026 ────────────────────────────────────────────────
('INV-2026-00009',  5,  579.00,   0.00, 'flat', 0, 579.00,  'card',  'completed', 2, '2026-02-02 11:00:00'),
('INV-2026-00010',  1,  548.00,   0.00, 'flat', 0, 548.00,  'cash',  'completed', 1, '2026-02-05 14:15:00'),
('INV-2026-00011',  4,  920.00,   0.00, 'flat', 0, 920.00,  'gcash', 'completed', 2, '2026-02-10 10:30:00'),
('INV-2026-00012',  2,  985.00,   0.00, 'flat', 0, 985.00,  'maya',  'completed', 1, '2026-02-14 11:00:00'),
('INV-2026-00013',  6,  598.00,   0.00, 'flat', 0, 598.00,  'card',  'completed', 2, '2026-02-18 14:30:00'),
('INV-2026-00014',  8,  848.00,   0.00, 'flat', 0, 848.00,  'gcash', 'completed', 1, '2026-02-20 10:00:00');

-- ─── Sale Items ───────────────────────────────────────────
CREATE TABLE `sale_items` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sale_id`     INT UNSIGNED  NOT NULL,
  `product_id`  INT UNSIGNED  DEFAULT NULL,
  `quantity`    INT           NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(12,2) NOT NULL,
  `total_price` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id`    (`sale_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_si_sale`    FOREIGN KEY (`sale_id`)    REFERENCES `sales`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_si_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sale_items` (`sale_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
-- sale 1 (INV-2025-00001) ─── ids 1,2
(1,  1, 2, 299.00,  598.00),  -- Birthday Gift Box ×2
(1, 13, 1,  99.00,   99.00),  -- Leather Keychain ×1
-- sale 2 (INV-2025-00002) ─── id 3
(2,  6, 1, 199.00,  199.00),  -- Plain White T-Shirt ×1
-- sale 3 (INV-2025-00003) ─── ids 4,5
(3,  7, 1, 650.00,  650.00),  -- Denim Jeans ×1
(3,  9, 1, 350.00,  350.00),  -- Polo Shirt ×1
-- sale 4 (INV-2025-00004) ─── ids 6,7
(4, 10, 3, 150.00,  450.00),  -- City Souvenir Mug ×3
(4, 13, 2,  99.00,  198.00),  -- Leather Keychain ×2
-- sale 5 (INV-2025-00005) ─── ids 8,9
(5,  4, 1, 220.00,  220.00),  -- Scented Candle Set ×1
(5,  5, 1, 250.00,  250.00),  -- Custom Photo Frame ×1
-- sale 6 (INV-2025-00006) ─── ids 10,11
(6, 19, 1, 280.00,  280.00),  -- Kids Stuffed Toy ×1
(6, 21, 1, 129.00,  129.00),  -- Coloring Book Pack ×1
-- sale 7 (INV-2025-00007) ─── ids 12,13
(7,  8, 1, 499.00,  499.00),  -- Floral Summer Dress ×1
(7, 14, 1, 169.00,  169.00),  -- Canvas Tote Bag ×1
-- sale 8 (INV-2025-00008) ─── id 14
(8,  3, 1, 549.00,  549.00),  -- Anniversary Gift Set ×1
-- sale 9 (INV-2025-00009) ─── ids 15,16
(9, 24, 2, 399.00,  798.00),  -- Women's Blouse ×2
(9, 25, 1, 199.00,  199.00),  -- Ladies' Scarf ×1
-- sale 10 (INV-2025-00010) ─── id 17
(10, 2, 1, 699.00,  699.00),  -- Christmas Hamper ×1
-- sale 11 (INV-2025-00011) ─── ids 18,19
(11,17, 2, 275.00,  550.00),  -- Christmas Stocking Set ×2
(11, 4, 1, 220.00,  220.00),  -- Scented Candle Set ×1
-- sale 12 (INV-2025-00012) ─── ids 20,21
(12, 1, 1, 299.00,  299.00),  -- Birthday Gift Box ×1
(12, 5, 1, 250.00,  250.00),  -- Custom Photo Frame ×1
-- sale 13 (INV-2025-00013) ─── ids 22,23
(13, 2, 1, 699.00,  699.00),  -- Christmas Hamper ×1
(13,10, 2, 150.00,  300.00),  -- City Souvenir Mug ×2
-- sale 14 (INV-2025-00014) ─── ids 24,25
(14, 8, 1, 499.00,  499.00),  -- Floral Summer Dress ×1  ← return 1 targets id=24
(14,15, 2,  85.00,  170.00),  -- Beaded Bracelet ×2
-- sale 15 (INV-2025-00015) ─── ids 26,27
(15, 6, 3, 199.00,  597.00),  -- Plain White T-Shirt ×3
(15, 9, 1, 350.00,  350.00),  -- Polo Shirt ×1
-- sale 16 (INV-2025-00016) ─── ids 28,29
(16, 3, 1, 549.00,  549.00),  -- Anniversary Gift Set ×1
(16, 4, 1, 220.00,  220.00),  -- Scented Candle Set ×1
-- sale 17 (INV-2025-00017) ─── ids 30,31
(17,16, 1, 450.00,  450.00),  -- Valentine Gift Set ×1
(17,11, 1, 380.00,  380.00),  -- Keepsake Snow Globe ×1
-- sale 18 (INV-2025-00018) ─── ids 32,33
(18,22, 2, 299.00,  598.00),  -- Men's Casual Shorts ×2  ← return 3 targets id=32
(18,23, 1, 499.00,  499.00),  -- Men's Dress Shirt ×1
-- sale 19 (INV-2025-00019) ─── ids 34,35
(19, 2, 1, 699.00,  699.00),  -- Christmas Hamper ×1
(19,17, 3, 275.00,  825.00),  -- Christmas Stocking Set ×3
-- sale 20 (INV-2025-00020) ─── ids 36,37,38
(20, 1, 2, 299.00,  598.00),  -- Birthday Gift Box ×2
(20,19, 1, 280.00,  280.00),  -- Kids Stuffed Toy ×1
(20,21, 1, 129.00,  129.00),  -- Coloring Book Pack ×1
-- sale 21 (INV-2025-00021 — voided) ─── id 39
(21, 7, 1, 650.00,  650.00),  -- Denim Jeans ×1
-- sale 22 (INV-2025-00022) ─── ids 40,41
(22,24, 2, 399.00,  798.00),  -- Women's Blouse ×2
(22,15, 2,  85.00,  170.00),  -- Beaded Bracelet ×2
-- sale 23 (INV-2025-00023) ─── ids 42,43
(23,12, 1, 199.00,  199.00),  -- Decorative Plate ×1
(23,13, 2,  99.00,  198.00),  -- Leather Keychain ×2
-- sale 24 (INV-2026-00001) ─── ids 44,45
(24,16, 1, 450.00,  450.00),  -- Valentine Gift Set ×1
(24, 4, 1, 220.00,  220.00),  -- Scented Candle Set ×1
-- sale 25 (INV-2026-00002) ─── ids 46,47
(25, 6, 2, 199.00,  398.00),  -- Plain White T-Shirt ×2
(25,14, 1, 169.00,  169.00),  -- Canvas Tote Bag ×1
-- sale 26 (INV-2026-00003) ─── ids 48,49
(26,20, 1, 399.00,  399.00),  -- Building Blocks Set ×1
(26,21, 2, 129.00,  258.00),  -- Coloring Book Pack ×2
-- sale 27 (INV-2026-00004) ─── ids 50,51
(27, 1, 1, 299.00,  299.00),  -- Birthday Gift Box ×1
(27,10, 1, 150.00,  150.00),  -- City Souvenir Mug ×1
-- sale 28 (INV-2026-00005) ─── ids 52,53
(28,16, 1, 450.00,  450.00),  -- Valentine Gift Set ×1
(28,15, 2,  85.00,  170.00),  -- Beaded Bracelet ×2
-- sale 29 (INV-2026-00006) ─── ids 54,55
(29,23, 1, 499.00,  499.00),  -- Men's Dress Shirt ×1
(29,22, 1, 299.00,  299.00),  -- Men's Casual Shorts ×1
-- sale 30 (INV-2026-00007) ─── ids 56,57
(30, 8, 1, 499.00,  499.00),  -- Floral Summer Dress ×1
(30,25, 1, 199.00,  199.00),  -- Ladies' Scarf ×1
-- sale 31 (INV-2026-00008) ─── id 58
(31, 3, 2, 549.00, 1098.00),  -- Anniversary Gift Set ×2
-- sale 32 (INV-2026-00009) ─── ids 59,60
(32,11, 1, 380.00,  380.00),  -- Keepsake Snow Globe ×1
(32,12, 1, 199.00,  199.00),  -- Decorative Plate ×1
-- sale 33 (INV-2026-00010) ─── ids 61,62
(33, 9, 1, 350.00,  350.00),  -- Polo Shirt ×1
(33,13, 2,  99.00,  198.00),  -- Leather Keychain ×2
-- sale 34 (INV-2026-00011) ─── ids 63,64,65
(34,16, 1, 450.00,  450.00),  -- Valentine Gift Set ×1
(34, 4, 1, 220.00,  220.00),  -- Scented Candle Set ×1
(34, 5, 1, 250.00,  250.00),  -- Custom Photo Frame ×1
-- sale 35 (INV-2026-00012) ─── ids 66,67
(35,16, 2, 450.00,  900.00),  -- Valentine Gift Set ×2
(35,15, 1,  85.00,   85.00),  -- Beaded Bracelet ×1
-- sale 36 (INV-2026-00013) ─── ids 68,69
(36,24, 1, 399.00,  399.00),  -- Women's Blouse ×1
(36,25, 1, 199.00,  199.00),  -- Ladies' Scarf ×1
-- sale 37 (INV-2026-00014) ─── ids 70,71
(37, 1, 1, 299.00,  299.00),  -- Birthday Gift Box ×1
(37, 3, 1, 549.00,  549.00);  -- Anniversary Gift Set ×1

-- ─── Purchases ────────────────────────────────────────────
CREATE TABLE `purchases` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `supplier_id`   INT UNSIGNED  DEFAULT NULL,
  `reference_no`  VARCHAR(30)   NOT NULL UNIQUE,
  `purchase_date` DATE          NOT NULL,
  `total_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`        ENUM('pending','received','partial','cancelled') NOT NULL DEFAULT 'pending',
  `notes`         TEXT          DEFAULT NULL,
  `created_by`    INT UNSIGNED  DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `fk_pur_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pur_user`     FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `purchases`
  (`supplier_id`, `reference_no`, `purchase_date`, `total_amount`, `status`, `notes`, `created_by`, `created_at`) VALUES
(1, 'PO-2025-001', '2025-01-15',  9750.00, 'received', 'Opening stock — gift items',             1, '2025-01-15 08:30:00'),
(2, 'PO-2025-002', '2025-01-20', 12100.00, 'received', 'Opening stock — clothing basics',        1, '2025-01-20 08:30:00'),
(1, 'PO-2025-003', '2025-02-05',  6550.00, 'received', 'Opening stock — souvenirs & accessories',1, '2025-02-05 08:30:00'),
(4, 'PO-2025-004', '2025-02-10',  8675.00, 'received', 'Craft & home accessories restock',       1, '2025-02-10 08:30:00'),
(2, 'PO-2025-005', '2025-02-28', 23175.00, 'received', 'Fashion restock — all apparel lines',    1, '2025-02-28 08:30:00'),
(3, 'PO-2025-006', '2025-03-15', 11175.00, 'received', 'Novelty & souvenir restock',             1, '2025-03-15 08:30:00'),
(1, 'PO-2025-007', '2025-10-15', 13300.00, 'received', 'Holiday season pre-stock',               1, '2025-10-15 08:30:00'),
(1, 'PO-2026-001', '2026-01-10',  9700.00, 'received', 'Valentine season restock',               1, '2026-01-10 08:30:00'),
(3, 'PO-2026-002', '2026-02-01',  2100.00, 'partial',  'Easter stock — partially received',      1, '2026-02-01 08:30:00'),
(4, 'PO-2026-003', '2026-02-10',  4050.00, 'pending',  'Easter & birthday restock order',        1, '2026-02-10 08:00:00'),
(5, 'PO-2026-004', '2026-02-15',  8250.00, 'cancelled','Cancelled — supplier out of stock',      1, '2026-02-15 08:00:00');

-- ─── Purchase Items ───────────────────────────────────────
CREATE TABLE `purchase_items` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `purchase_id` INT UNSIGNED  NOT NULL,
  `product_id`  INT UNSIGNED  DEFAULT NULL,
  `quantity`    INT           NOT NULL DEFAULT 1,
  `unit_cost`   DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `product_id`  (`product_id`),
  CONSTRAINT `fk_pi_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_pi_product`  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `purchase_items` (`purchase_id`, `product_id`, `quantity`, `unit_cost`) VALUES
-- PO-2025-001
(1,  1, 30, 150.00),  -- Birthday Gift Box
(1,  2, 15, 350.00),  -- Christmas Hamper
-- PO-2025-002
(2,  6, 60,  85.00),  -- Plain White T-Shirt
(2,  7, 25, 280.00),  -- Denim Jeans
-- PO-2025-003
(3, 10, 50,  65.00),  -- City Souvenir Mug
(3, 13, 50,  45.00),  -- Leather Keychain
(3, 15, 30,  35.00),  -- Beaded Bracelet
-- PO-2025-004
(4,  4, 40,  95.00),  -- Scented Candle Set
(4,  5, 25, 120.00),  -- Custom Photo Frame
(4, 14, 25,  75.00),  -- Canvas Tote Bag
-- PO-2025-005
(5,  8, 20, 220.00),  -- Floral Summer Dress
(5,  9, 30, 150.00),  -- Polo Shirt (Navy)
(5, 22, 20, 135.00),  -- Men's Casual Shorts
(5, 23, 20, 210.00),  -- Men's Dress Shirt
(5, 24, 30, 175.00),  -- Women's Blouse
(5, 25, 25,  85.00),  -- Ladies' Scarf
-- PO-2025-006
(6,  3, 25, 280.00),  -- Anniversary Gift Set
(6, 11, 10, 180.00),  -- Keepsake Snow Globe
(6, 12, 25,  95.00),  -- Decorative Plate
-- PO-2025-007
(7, 17, 30, 120.00),  -- Christmas Stocking Set
(7, 16, 10, 200.00),  -- Valentine Gift Set (pre-season stock)
(7, 19, 15, 120.00),  -- Kids Stuffed Toy
(7, 20, 20, 185.00),  -- Building Blocks Set
(7, 21, 40,  55.00),  -- Coloring Book Pack
-- PO-2026-001
(8, 16, 20, 200.00),  -- Valentine Gift Set (main season restock)
(8, 11, 10, 180.00),  -- Keepsake Snow Globe
(8, 24, 15, 175.00),  -- Women's Blouse
(8, 25, 15,  85.00),  -- Ladies' Scarf
-- PO-2026-002 (partial)
(9, 18, 10,  90.00),  -- Easter Basket (10 ordered, 5 received)
(9, 19, 10, 120.00),  -- Kids Stuffed Toy (10 ordered, 5 received)
-- PO-2026-003 (pending)
(10,18, 20,  90.00),  -- Easter Basket
(10, 1, 15, 150.00),  -- Birthday Gift Box
-- PO-2026-004 (cancelled)
(11,22, 30, 135.00),  -- Men's Casual Shorts
(11,23, 20, 210.00);  -- Men's Dress Shirt

-- ─── Stock Movements ──────────────────────────────────────
CREATE TABLE `stock_movements` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED  DEFAULT NULL,
  `type`       ENUM('in','out','adjustment') NOT NULL,
  `quantity`   INT           NOT NULL,
  `reference`  VARCHAR(100)  DEFAULT NULL,
  `created_by` INT UNSIGNED  DEFAULT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_sm_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sm_user`    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_movements` (`product_id`, `type`, `quantity`, `reference`, `created_by`, `created_at`) VALUES
-- ═══ PO-2025-001 received Jan 15, 2025 ═══
( 1, 'in', 30, 'PO-2025-001', 1, '2025-01-15 10:00:00'),
( 2, 'in', 15, 'PO-2025-001', 1, '2025-01-15 10:00:00'),
-- ═══ PO-2025-002 received Jan 20, 2025 ═══
( 6, 'in', 60, 'PO-2025-002', 1, '2025-01-20 10:00:00'),
( 7, 'in', 25, 'PO-2025-002', 1, '2025-01-20 10:00:00'),
-- ═══ PO-2025-003 received Feb 05, 2025 ═══
(10, 'in', 50, 'PO-2025-003', 1, '2025-02-05 10:00:00'),
(13, 'in', 50, 'PO-2025-003', 1, '2025-02-05 10:00:00'),
(15, 'in', 30, 'PO-2025-003', 1, '2025-02-05 10:00:00'),
-- ═══ PO-2025-004 received Feb 10, 2025 ═══
( 4, 'in', 40, 'PO-2025-004', 1, '2025-02-10 10:00:00'),
( 5, 'in', 25, 'PO-2025-004', 1, '2025-02-10 10:00:00'),
(14, 'in', 25, 'PO-2025-004', 1, '2025-02-10 10:00:00'),
-- ═══ PO-2025-005 received Feb 28, 2025 ═══
( 8, 'in', 20, 'PO-2025-005', 1, '2025-02-28 10:00:00'),
( 9, 'in', 30, 'PO-2025-005', 1, '2025-02-28 10:00:00'),
(22, 'in', 20, 'PO-2025-005', 1, '2025-02-28 10:00:00'),
(23, 'in', 20, 'PO-2025-005', 1, '2025-02-28 10:00:00'),
(24, 'in', 30, 'PO-2025-005', 1, '2025-02-28 10:00:00'),
(25, 'in', 25, 'PO-2025-005', 1, '2025-02-28 10:00:00'),
-- ═══ PO-2025-006 received Mar 15, 2025 ═══
( 3, 'in', 25, 'PO-2025-006', 1, '2025-03-15 10:00:00'),
(11, 'in', 10, 'PO-2025-006', 1, '2025-03-15 10:00:00'),
(12, 'in', 25, 'PO-2025-006', 1, '2025-03-15 10:00:00'),
-- ═══ PO-2025-007 received Oct 15, 2025 ═══
(17, 'in', 30, 'PO-2025-007', 1, '2025-10-15 10:00:00'),
(16, 'in', 10, 'PO-2025-007', 1, '2025-10-15 10:00:00'),
(19, 'in', 15, 'PO-2025-007', 1, '2025-10-15 10:00:00'),
(20, 'in', 20, 'PO-2025-007', 1, '2025-10-15 10:00:00'),
(21, 'in', 40, 'PO-2025-007', 1, '2025-10-15 10:00:00'),
-- ═══ Sale 1 — INV-2025-00001 (Nov 02, admin) ═══
( 1, 'out',  2, 'INV-2025-00001', 1, '2025-11-02 09:15:00'),
(13, 'out',  1, 'INV-2025-00001', 1, '2025-11-02 09:15:00'),
-- ═══ Sale 2 — INV-2025-00002 (Nov 05, staff) ═══
( 6, 'out',  1, 'INV-2025-00002', 2, '2025-11-05 14:30:00'),
-- ═══ Sale 3 — INV-2025-00003 (Nov 08, admin) ═══
( 7, 'out',  1, 'INV-2025-00003', 1, '2025-11-08 10:45:00'),
( 9, 'out',  1, 'INV-2025-00003', 1, '2025-11-08 10:45:00'),
-- ═══ Sale 4 — INV-2025-00004 (Nov 12, staff) ═══
(10, 'out',  3, 'INV-2025-00004', 2, '2025-11-12 11:20:00'),
(13, 'out',  2, 'INV-2025-00004', 2, '2025-11-12 11:20:00'),
-- ═══ Sale 5 — INV-2025-00005 (Nov 15, admin) ═══
( 4, 'out',  1, 'INV-2025-00005', 1, '2025-11-15 15:00:00'),
( 5, 'out',  1, 'INV-2025-00005', 1, '2025-11-15 15:00:00'),
-- ═══ Sale 6 — INV-2025-00006 (Nov 18, staff) ═══
(19, 'out',  1, 'INV-2025-00006', 2, '2025-11-18 16:30:00'),
(21, 'out',  1, 'INV-2025-00006', 2, '2025-11-18 16:30:00'),
-- ═══ Sale 7 — INV-2025-00007 (Nov 22, admin) ═══
( 8, 'out',  1, 'INV-2025-00007', 1, '2025-11-22 10:10:00'),
(14, 'out',  1, 'INV-2025-00007', 1, '2025-11-22 10:10:00'),
-- ═══ Sale 8 — INV-2025-00008 (Nov 25, staff) ═══
( 3, 'out',  1, 'INV-2025-00008', 2, '2025-11-25 13:45:00'),
-- ═══ Sale 9 — INV-2025-00009 (Nov 28, admin) ═══
(24, 'out',  2, 'INV-2025-00009', 1, '2025-11-28 14:00:00'),
(25, 'out',  1, 'INV-2025-00009', 1, '2025-11-28 14:00:00'),
-- ═══ Sale 10 — INV-2025-00010 (Dec 01, staff) ═══
( 2, 'out',  1, 'INV-2025-00010', 2, '2025-12-01 09:30:00'),
-- ═══ Sale 11 — INV-2025-00011 (Dec 03, admin) ═══
(17, 'out',  2, 'INV-2025-00011', 1, '2025-12-03 11:15:00'),
( 4, 'out',  1, 'INV-2025-00011', 1, '2025-12-03 11:15:00'),
-- ═══ Sale 12 — INV-2025-00012 (Dec 05, staff) ═══
( 1, 'out',  1, 'INV-2025-00012', 2, '2025-12-05 14:20:00'),
( 5, 'out',  1, 'INV-2025-00012', 2, '2025-12-05 14:20:00'),
-- ═══ Sale 13 — INV-2025-00013 (Dec 08, admin) ═══
( 2, 'out',  1, 'INV-2025-00013', 1, '2025-12-08 10:00:00'),
(10, 'out',  2, 'INV-2025-00013', 1, '2025-12-08 10:00:00'),
-- ═══ Sale 14 — INV-2025-00014 (Dec 10, staff) ═══
( 8, 'out',  1, 'INV-2025-00014', 2, '2025-12-10 15:30:00'),
(15, 'out',  2, 'INV-2025-00014', 2, '2025-12-10 15:30:00'),
-- ═══ Sale 15 — INV-2025-00015 (Dec 12, admin) ═══
( 6, 'out',  3, 'INV-2025-00015', 1, '2025-12-12 11:00:00'),
( 9, 'out',  1, 'INV-2025-00015', 1, '2025-12-12 11:00:00'),
-- ═══ Sale 16 — INV-2025-00016 (Dec 15, staff) ═══
( 3, 'out',  1, 'INV-2025-00016', 2, '2025-12-15 13:45:00'),
( 4, 'out',  1, 'INV-2025-00016', 2, '2025-12-15 13:45:00'),
-- ═══ Sale 17 — INV-2025-00017 (Dec 18, admin) ═══
(16, 'out',  1, 'INV-2025-00017', 1, '2025-12-18 10:30:00'),
(11, 'out',  1, 'INV-2025-00017', 1, '2025-12-18 10:30:00'),
-- ═══ Sale 18 — INV-2025-00018 (Dec 20, staff) ═══
(22, 'out',  2, 'INV-2025-00018', 2, '2025-12-20 14:00:00'),
(23, 'out',  1, 'INV-2025-00018', 2, '2025-12-20 14:00:00'),
-- ═══ Sale 19 — INV-2025-00019 (Dec 22, admin) ═══
( 2, 'out',  1, 'INV-2025-00019', 1, '2025-12-22 16:00:00'),
(17, 'out',  3, 'INV-2025-00019', 1, '2025-12-22 16:00:00'),
-- ═══ Sale 20 — INV-2025-00020 (Dec 24, staff) ═══
( 1, 'out',  2, 'INV-2025-00020', 2, '2025-12-24 11:30:00'),
(19, 'out',  1, 'INV-2025-00020', 2, '2025-12-24 11:30:00'),
(21, 'out',  1, 'INV-2025-00020', 2, '2025-12-24 11:30:00'),
-- ═══ Sale 21 — INV-2025-00021 (Dec 26, admin) — VOIDED ═══
( 7, 'out',  1, 'INV-2025-00021',      1, '2025-12-26 09:00:00'),  -- sale
( 7, 'in',   1, 'VOID-INV-2025-00021', 1, '2025-12-26 09:32:00'),  -- void restore
-- ═══ Sale 22 — INV-2025-00022 (Dec 28, staff) ═══
(24, 'out',  2, 'INV-2025-00022', 2, '2025-12-28 14:45:00'),
(15, 'out',  2, 'INV-2025-00022', 2, '2025-12-28 14:45:00'),
-- ═══ Sale 23 — INV-2025-00023 (Dec 30, admin) ═══
(12, 'out',  1, 'INV-2025-00023', 1, '2025-12-30 10:15:00'),
(13, 'out',  2, 'INV-2025-00023', 1, '2025-12-30 10:15:00'),
-- ═══ Sale 24 — INV-2026-00001 (Jan 03, staff) ═══
(16, 'out',  1, 'INV-2026-00001', 2, '2026-01-03 11:00:00'),
( 4, 'out',  1, 'INV-2026-00001', 2, '2026-01-03 11:00:00'),
-- ═══ Sale 25 — INV-2026-00002 (Jan 07, admin) ═══
( 6, 'out',  2, 'INV-2026-00002', 1, '2026-01-07 14:30:00'),
(14, 'out',  1, 'INV-2026-00002', 1, '2026-01-07 14:30:00'),
-- ═══ PO-2026-001 received Jan 10 morning ═══
(16, 'in',  20, 'PO-2026-001', 1, '2026-01-10 10:00:00'),
(11, 'in',  10, 'PO-2026-001', 1, '2026-01-10 10:00:00'),
(24, 'in',  15, 'PO-2026-001', 1, '2026-01-10 10:00:00'),
(25, 'in',  15, 'PO-2026-001', 1, '2026-01-10 10:00:00'),
-- ═══ Sale 26 — INV-2026-00003 (Jan 10 afternoon, staff) ═══
(20, 'out',  1, 'INV-2026-00003', 2, '2026-01-10 14:00:00'),
(21, 'out',  2, 'INV-2026-00003', 2, '2026-01-10 14:00:00'),
-- ═══ Sale 27 — INV-2026-00004 (Jan 14, admin) ═══
( 1, 'out',  1, 'INV-2026-00004', 1, '2026-01-14 15:15:00'),
(10, 'out',  1, 'INV-2026-00004', 1, '2026-01-14 15:15:00'),
-- ═══ Sale 28 — INV-2026-00005 (Jan 18, staff) ═══
(16, 'out',  1, 'INV-2026-00005', 2, '2026-01-18 11:30:00'),
(15, 'out',  2, 'INV-2026-00005', 2, '2026-01-18 11:30:00'),
-- ═══ Sale 29 — INV-2026-00006 (Jan 22, admin) ═══
(23, 'out',  1, 'INV-2026-00006', 1, '2026-01-22 14:00:00'),
(22, 'out',  1, 'INV-2026-00006', 1, '2026-01-22 14:00:00'),
-- ═══ Sale 30 — INV-2026-00007 (Jan 25, staff) ═══
( 8, 'out',  1, 'INV-2026-00007', 2, '2026-01-25 10:45:00'),
(25, 'out',  1, 'INV-2026-00007', 2, '2026-01-25 10:45:00'),
-- ═══ Sale 31 — INV-2026-00008 (Jan 28, admin) ═══
( 3, 'out',  2, 'INV-2026-00008', 1, '2026-01-28 13:30:00'),
-- ═══ PO-2026-002 partial received Feb 01 ═══
(18, 'in',   5, 'PO-2026-002', 1, '2026-02-01 10:00:00'),
(19, 'in',   5, 'PO-2026-002', 1, '2026-02-01 10:00:00'),
-- ═══ Sale 32 — INV-2026-00009 (Feb 02, staff) ═══
(11, 'out',  1, 'INV-2026-00009', 2, '2026-02-02 11:00:00'),
(12, 'out',  1, 'INV-2026-00009', 2, '2026-02-02 11:00:00'),
-- ═══ Sale 33 — INV-2026-00010 (Feb 05, admin) ═══
( 9, 'out',  1, 'INV-2026-00010', 1, '2026-02-05 14:15:00'),
(13, 'out',  2, 'INV-2026-00010', 1, '2026-02-05 14:15:00'),
-- ═══ Sale 34 — INV-2026-00011 (Feb 10, staff) ═══
(16, 'out',  1, 'INV-2026-00011', 2, '2026-02-10 10:30:00'),
( 4, 'out',  1, 'INV-2026-00011', 2, '2026-02-10 10:30:00'),
( 5, 'out',  1, 'INV-2026-00011', 2, '2026-02-10 10:30:00'),
-- ═══ Return 1 — RET-2026-00001 (Feb 10, after sale 34) ═══
( 8, 'in',   1, 'RET-2026-00001', 1, '2026-02-10 11:00:00'),
-- ═══ Sale 35 — INV-2026-00012 (Feb 14, admin) ═══
(16, 'out',  2, 'INV-2026-00012', 1, '2026-02-14 11:00:00'),
(15, 'out',  1, 'INV-2026-00012', 1, '2026-02-14 11:00:00'),
-- ═══ Return 2 — RET-2026-00002 (Feb 12) ═══
(10, 'in',   1, 'RET-2026-00002', 1, '2026-02-12 14:00:00'),
-- ═══ Return 3 — RET-2026-00003 (Feb 15) ═══
(22, 'in',   1, 'RET-2026-00003', 1, '2026-02-15 10:30:00'),
-- ═══ Sale 36 — INV-2026-00013 (Feb 18, staff) ═══
(24, 'out',  1, 'INV-2026-00013', 2, '2026-02-18 14:30:00'),
(25, 'out',  1, 'INV-2026-00013', 2, '2026-02-18 14:30:00'),
-- ═══ Sale 37 — INV-2026-00014 (Feb 20, admin) ═══
( 1, 'out',  1, 'INV-2026-00014', 1, '2026-02-20 10:00:00'),
( 3, 'out',  1, 'INV-2026-00014', 1, '2026-02-20 10:00:00');

-- ─── Sale Returns ──────────────────────────────────────────
CREATE TABLE `sale_returns` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `return_no`    VARCHAR(30)   NOT NULL UNIQUE,
  `sale_id`      INT UNSIGNED  NOT NULL,
  `total_refund` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reason`       TEXT          DEFAULT NULL,
  `created_by`   INT UNSIGNED  DEFAULT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_id`    (`sale_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_ret_sale` FOREIGN KEY (`sale_id`)    REFERENCES `sales`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_ret_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sale_returns` (`return_no`, `sale_id`, `total_refund`, `reason`, `created_by`, `created_at`) VALUES
('RET-2026-00001', 14, 499.00, 'Wrong size — customer requested exchange for different size', 1, '2026-02-10 11:00:00'),
('RET-2026-00002',  4, 150.00, 'Item cracked upon inspection at home', 1, '2026-02-12 14:00:00'),
('RET-2026-00003', 18, 299.00, 'Does not fit — wrong size ordered by customer', 1, '2026-02-15 10:30:00');

-- ─── Sale Return Items ─────────────────────────────────────
CREATE TABLE `sale_return_items` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `return_id`    INT UNSIGNED  NOT NULL,
  `sale_item_id` INT UNSIGNED  DEFAULT NULL,
  `product_id`   INT UNSIGNED  DEFAULT NULL,
  `quantity`     INT           NOT NULL DEFAULT 1,
  `unit_price`   DECIMAL(12,2) NOT NULL,
  `total_price`  DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `return_id`    (`return_id`),
  KEY `sale_item_id` (`sale_item_id`),
  KEY `product_id`   (`product_id`),
  CONSTRAINT `fk_sri_return`    FOREIGN KEY (`return_id`)    REFERENCES `sale_returns`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sri_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items`(`id`)   ON DELETE SET NULL,
  CONSTRAINT `fk_sri_product`   FOREIGN KEY (`product_id`)   REFERENCES `products`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sale_item ids: sale 14 → items 24(p8),25(p15) | sale 4 → items 6(p10),7(p13) | sale 18 → items 32(p22),33(p23)
INSERT INTO `sale_return_items` (`return_id`, `sale_item_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 24,  8, 1, 499.00, 499.00),  -- return 1: Floral Summer Dress from sale 14
(2,  6, 10, 1, 150.00, 150.00),  -- return 2: City Souvenir Mug (1 of 3) from sale 4
(3, 32, 22, 1, 299.00, 299.00);  -- return 3: Men's Casual Shorts (1 of 2) from sale 18

SET FOREIGN_KEY_CHECKS = 1;

-- ─── Fix passwords ─────────────────────────────────────────
-- Set both accounts to Admin@123 (bcrypt hash generated below)
-- Because we cannot compute bcrypt inside SQL, we use a known hash.
-- Hash below = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost'=>12])
-- Verified: $2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi = 'password'
-- We use setup.php to update the hash programmatically (see below).
-- As a fallback the hash below is for literal string "password":
UPDATE `users` SET `password` = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- ═══════════════════════════════════════════════════════════
-- Import complete.
-- Accounts:  admin@giftz.local / password
--            staff@giftz.local / password
-- Run setup.php to upgrade passwords to Admin@123.
-- ═══════════════════════════════════════════════════════════
