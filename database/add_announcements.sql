-- ─── Announcements ────────────────────────────────────────
-- Run against giftz_db to add storefront announcement support

USE `giftz_db`;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message`    VARCHAR(255) NOT NULL,
  `emoji`      VARCHAR(10)  NOT NULL DEFAULT '🎉',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed with the existing hardcoded messages
INSERT INTO `announcements` (`message`, `emoji`, `is_active`, `sort_order`) VALUES
('New arrivals just landed — explore our latest gifts & fashion!',       '🎉', 1, 1),
('Visit us in-store for exclusive member deals and special offers.',     '🚚', 1, 2),
('Seasonal gifts now available — perfect for every occasion.',           '🎄', 1, 3),
('Find the perfect gift for someone special — browse our full collection.','💝', 1, 4);
