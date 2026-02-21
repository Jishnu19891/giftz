-- ═══════════════════════════════════════════════════════════
-- Giftz — Migration: Add Returns Tables
-- Run this against giftz_db after the initial schema is in place.
-- ═══════════════════════════════════════════════════════════

USE `giftz_db`;

-- ─── Return Headers ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sale_returns` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `return_no`    VARCHAR(30)   NOT NULL UNIQUE,
  `sale_id`      INT UNSIGNED  NOT NULL,
  `total_refund` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reason`       TEXT          DEFAULT NULL,
  `created_by`   INT UNSIGNED  DEFAULT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_id`   (`sale_id`),
  KEY `created_by`(`created_by`),
  CONSTRAINT `fk_ret_sale` FOREIGN KEY (`sale_id`)    REFERENCES `sales`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_ret_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Return Line Items ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sale_return_items` (
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
