-- Database Schema for myCost v2.0 (Multi-User & Itemized Breakdown)

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Transactions Table
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL DEFAULT 1,
    `type` ENUM('pemasukan', 'pengeluaran') NOT NULL DEFAULT 'pengeluaran',
    `amount` DECIMAL(15,2) NOT NULL,
    `subtotal` DECIMAL(15,2) NULL DEFAULT 0.00,
    `discount` DECIMAL(15,2) NULL DEFAULT 0.00,
    `tax` DECIMAL(15,2) NULL DEFAULT 0.00,
    `category` VARCHAR(50) NOT NULL DEFAULT 'Lainnya',
    `transaction_date` DATE NOT NULL,
    `notes` TEXT NULL,
    `receipt_image_url` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`transaction_date`),
    INDEX `idx_type` (`type`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Transaction Items Table (Rincian Barang & Diskon per Nota)
CREATE TABLE IF NOT EXISTS `transaction_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `qty` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_trans_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
