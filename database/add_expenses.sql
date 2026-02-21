-- ═══════════════════════════════════════════════════════════
-- Giftz — Migration: Add Expense Tracking Tables
-- Run against giftz_db after the initial schema is in place.
-- ═══════════════════════════════════════════════════════════

USE `giftz_db`;

-- ─── Expense Categories ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `color`      VARCHAR(30)  NOT NULL DEFAULT 'badge-secondary',
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `expense_categories` (`name`, `color`, `sort_order`) VALUES
('Rent & Utilities',   'badge-info',      1),
('Salaries & Wages',   'badge-purple',    2),
('Office Supplies',    'badge-warning',   3),
('Marketing & Ads',    'badge-success',   4),
('Equipment',          'badge-info',      5),
('Maintenance',        'badge-warning',   6),
('Delivery & Freight', 'badge-info',      7),
('Professional Fees',  'badge-purple',    8),
('Miscellaneous',      'badge-secondary', 9);

-- ─── Expenses ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `reference_no`   VARCHAR(30)   NOT NULL UNIQUE,
  `category_id`    INT UNSIGNED  DEFAULT NULL,
  `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `description`    VARCHAR(255)  NOT NULL,
  `paid_to`        VARCHAR(150)  DEFAULT NULL,
  `payment_method` ENUM('cash','card','gcash','maya','bank','transfer') NOT NULL DEFAULT 'cash',
  `expense_date`   DATE          NOT NULL,
  `notes`          TEXT          DEFAULT NULL,
  `created_by`     INT UNSIGNED  DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id`  (`category_id`),
  KEY `expense_date`  (`expense_date`),
  CONSTRAINT `fk_exp_cat`  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_user` FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
