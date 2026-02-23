-- Visitor Logs Table
-- Run: http://localhost/giftz/visitors/migrate.php

CREATE TABLE IF NOT EXISTS `visitor_logs` (
  `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `session_hash` VARCHAR(64)      NOT NULL COMMENT 'SHA-256 of session_id for dedup',
  `ip_hash`      VARCHAR(64)      NOT NULL COMMENT 'SHA-256 of IP for privacy',
  `page`         VARCHAR(30)      NOT NULL COMMENT 'catalog|product|home',
  `page_id`      INT              NULL     COMMENT 'product id when page=product',
  `referrer`     VARCHAR(500)     NULL,
  `device`       ENUM('desktop','mobile','tablet','bot') NOT NULL DEFAULT 'desktop',
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_page`       (`page`),
  INDEX `idx_session`    (`session_hash`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
