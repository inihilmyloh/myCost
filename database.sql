-- Database Schema for myCost
-- Database Name: mycost_db (or finance_app)

CREATE DATABASE IF NOT EXISTS `mycost_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mycost_db`;

-- Transactions Table
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('pemasukan', 'pengeluaran') NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'Lainnya',
    `transaction_date` DATE NOT NULL,
    `notes` TEXT NULL,
    `receipt_image_url` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date` (`transaction_date`),
    INDEX `idx_type` (`type`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Initial Data
INSERT INTO `transactions` (`type`, `amount`, `category`, `transaction_date`, `notes`, `receipt_image_url`) VALUES
('pemasukan', 5000000.00, 'Gaji', CURDATE(), 'Gaji bulanan', NULL),
('pengeluaran', 45000.00, 'Makanan & Minuman', CURDATE(), 'Makan siang ayam geprek', NULL),
('pengeluaran', 150000.00, 'Belanja', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Belanja bulanan minimarket', NULL),
('pengeluaran', 50000.00, 'Transportasi', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Bensin motor', NULL),
('pemasukan', 500000.00, 'Freelance', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Project desain logo', NULL),
('pengeluaran', 75000.00, 'Hiburan', DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Nonton bioskop', NULL);
