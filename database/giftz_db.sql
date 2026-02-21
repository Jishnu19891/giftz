-- ═══════════════════════════════════════════════════════════
-- Giftz Inventory Management System — Database Setup
-- Import this file via phpMyAdmin or: mysql -u root < giftz_db.sql
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Create & Use Database ───────────────────────────────
CREATE DATABASE IF NOT EXISTS `giftz_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `giftz_db`;

-- ─── Users ────────────────────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)     NOT NULL,
  `email`      VARCHAR(150)     NOT NULL UNIQUE,
  `password`   VARCHAR(255)     NOT NULL,
  `role`       ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME         DEFAULT NULL,
  `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: admin@giftz.local / Admin@123
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES
('Admin User', 'admin@giftz.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),
('Staff Member', 'staff@giftz.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active');
-- Note: password hash above = 'password'. Run generate_hash.php to create proper Admin@123 hash.

-- ─── Categories ───────────────────────────────────────────
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)     NOT NULL,
  `type`       ENUM('gift','cloth','both') NOT NULL DEFAULT 'both',
  `parent_id`  INT UNSIGNED     DEFAULT NULL,
  `sort_order` INT              NOT NULL DEFAULT 0,
  `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`name`, `type`, `sort_order`) VALUES
('Gift Items',      'gift',  1),
('Clothing',        'cloth', 2),
('Souvenirs',       'gift',  3),
('Accessories',     'both',  4),
('Seasonal Gifts',  'gift',  5),
('Children\'s',     'both',  6),
('Men\'s Wear',     'cloth', 7),
('Women\'s Wear',   'cloth', 8);

-- ─── Suppliers ────────────────────────────────────────────
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(150)  NOT NULL,
  `contact_person` VARCHAR(100)  DEFAULT NULL,
  `phone`          VARCHAR(30)   DEFAULT NULL,
  `email`          VARCHAR(150)  DEFAULT NULL,
  `address`        TEXT          DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`name`, `contact_person`, `phone`, `email`) VALUES
('Gift World PH',   'Maria Santos',   '09171234567', 'maria@giftworldph.com'),
('Fashion Hub MNL', 'Juan dela Cruz',  '09281234567', 'juan@fashionhub.com');

-- ─── Products ─────────────────────────────────────────────
DROP TABLE IF EXISTS `products`;
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

INSERT INTO `products` (`sku`, `name`, `category_id`, `type`, `cost_price`, `selling_price`, `stock_qty`, `min_stock_level`) VALUES
('GFT-G0001', 'Birthday Gift Box',      1, 'gift',  150.00, 299.00, 25, 5),
('GFT-G0002', 'Christmas Hamper',       1, 'gift',  350.00, 699.00,  8, 3),
('CLT-C0001', 'Plain White T-Shirt',    2, 'cloth',  85.00, 199.00, 50, 10),
('CLT-C0002', 'Denim Jeans (Slim Fit)', 2, 'cloth', 280.00, 650.00, 20,  5),
('SVN-G0001', 'City Souvenir Mug',      3, 'gift',   65.00, 150.00, 40,  8),
('ACC-B0001', 'Leather Keychain',       4, 'both',   45.00,  99.00, 35,  8),
('SEA-G0001', 'Valentine Gift Set',     5, 'gift',  200.00, 450.00, 15,  5),
('CHD-B0001', 'Kids Stuffed Toy',       6, 'both',  120.00, 280.00,  2,  5);

-- ─── Customers ────────────────────────────────────────────
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150)  NOT NULL,
  `phone`      VARCHAR(30)   DEFAULT NULL,
  `email`      VARCHAR(150)  DEFAULT NULL,
  `address`    TEXT          DEFAULT NULL,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Walk-in customer (always id=1)
INSERT INTO `customers` (`name`, `phone`, `email`) VALUES
('Walk-in Customer', NULL, NULL),
('Anna Reyes',       '09191234567', 'anna@email.com'),
('Ben Torres',       '09201234567', NULL);

-- ─── Sales ────────────────────────────────────────────────
DROP TABLE IF EXISTS `sales`;
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

-- ─── Sale Items ───────────────────────────────────────────
DROP TABLE IF EXISTS `sale_items`;
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

-- ─── Purchases ────────────────────────────────────────────
DROP TABLE IF EXISTS `purchases`;
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

-- ─── Purchase Items ───────────────────────────────────────
DROP TABLE IF EXISTS `purchase_items`;
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

-- ─── Stock Movements ──────────────────────────────────────
DROP TABLE IF EXISTS `stock_movements`;
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

SET FOREIGN_KEY_CHECKS = 1;

-- ─── Done ─────────────────────────────────────────────────
-- Import complete. Default login: admin@giftz.local / password
-- Run: UPDATE users SET password = '$2y$12$...' WHERE email='admin@giftz.local';
-- Or use the generate_hash.php helper to create a proper hash for Admin@123
