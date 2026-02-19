<?php
/**
 * 초기 스키마 생성 - AltNET Ecount ERP 전체 테이블
 * 
 * 이미 존재하는 테이블은 건너뜁니다 (IF NOT EXISTS).
 * 새 환경에 배포할 때 전체 스키마가 자동 생성됩니다.
 */
return [
    'up' => [
        // ── users ──
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) DEFAULT NULL,
            `role` ENUM('admin','manager','user') NOT NULL DEFAULT 'user',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `login_fail_count` INT(11) NOT NULL DEFAULT 0,
            `locked_until` DATETIME DEFAULT NULL,
            `last_login` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── companies (거래처) ──
        "CREATE TABLE IF NOT EXISTS `companies` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(200) NOT NULL,
            `contact_person` VARCHAR(100) DEFAULT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `email` VARCHAR(150) DEFAULT NULL,
            `zipcode` VARCHAR(10) DEFAULT NULL,
            `address` VARCHAR(500) DEFAULT NULL,
            `address_detail` VARCHAR(300) DEFAULT NULL,
            `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_companies_deleted` (`is_deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── vendors (매입처) ──
        "CREATE TABLE IF NOT EXISTS `vendors` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(200) NOT NULL,
            `contact_person` VARCHAR(100) DEFAULT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `email` VARCHAR(150) DEFAULT NULL,
            `zipcode` VARCHAR(10) DEFAULT NULL,
            `address` VARCHAR(500) DEFAULT NULL,
            `address_detail` VARCHAR(300) DEFAULT NULL,
            `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_vendors_deleted` (`is_deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── sale_items (판매 제품코드) ──
        "CREATE TABLE IF NOT EXISTS `sale_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `sort_order` INT(11) NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_sale_items_deleted` (`is_deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── sales (매출) ──
        "CREATE TABLE IF NOT EXISTS `sales` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `sale_number` VARCHAR(20) NOT NULL,
            `sale_date` DATE NOT NULL,
            `company_id` INT(11) NOT NULL,
            `sale_item_id` INT(11) DEFAULT NULL,
            `total_amount` BIGINT(20) NOT NULL DEFAULT 0,
            `vat_amount` BIGINT(20) NOT NULL DEFAULT 0,
            `user_id` INT(11) NOT NULL,
            `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
            `deleted_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `sale_number` (`sale_number`),
            KEY `idx_sales_date` (`sale_date`),
            KEY `idx_sales_company` (`company_id`),
            KEY `idx_sales_deleted` (`is_deleted`),
            KEY `sale_item_id` (`sale_item_id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
            CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`),
            CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── sale_details (매출 상세) ──
        "CREATE TABLE IF NOT EXISTS `sale_details` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `sale_id` INT(11) NOT NULL,
            `product_name` VARCHAR(300) NOT NULL,
            `sale_item_id` INT(11) DEFAULT NULL,
            `unit_price` BIGINT(20) NOT NULL DEFAULT 0,
            `quantity` INT(11) NOT NULL DEFAULT 1,
            `subtotal` BIGINT(20) NOT NULL DEFAULT 0,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `sale_id` (`sale_id`),
            CONSTRAINT `sale_details_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── purchases (매입) ──
        "CREATE TABLE IF NOT EXISTS `purchases` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `purchase_number` VARCHAR(20) NOT NULL,
            `sale_id` INT(11) DEFAULT NULL,
            `purchase_date` DATE NOT NULL,
            `vendor_id` INT(11) NOT NULL,
            `total_amount` BIGINT(20) NOT NULL DEFAULT 0,
            `vat_amount` BIGINT(20) NOT NULL DEFAULT 0,
            `user_id` INT(11) NOT NULL,
            `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
            `deleted_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `purchase_number` (`purchase_number`),
            KEY `idx_purchases_date` (`purchase_date`),
            KEY `idx_purchases_vendor` (`vendor_id`),
            KEY `idx_purchases_sale` (`sale_id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
            CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
            CONSTRAINT `purchases_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── purchase_details (매입 상세) ──
        "CREATE TABLE IF NOT EXISTS `purchase_details` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `purchase_id` INT(11) NOT NULL,
            `product_name` VARCHAR(300) NOT NULL,
            `vendor_id` INT(11) DEFAULT NULL,
            `unit_price` BIGINT(20) NOT NULL DEFAULT 0,
            `quantity` INT(11) NOT NULL DEFAULT 1,
            `subtotal` BIGINT(20) NOT NULL DEFAULT 0,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `purchase_id` (`purchase_id`),
            CONSTRAINT `purchase_details_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── tax_invoices (세금계산서) ──
        "CREATE TABLE IF NOT EXISTS `tax_invoices` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `request_date` DATE NOT NULL,
            `company_id` INT(11) NOT NULL,
            `project_name` VARCHAR(300) NOT NULL DEFAULT '',
            `total_amount` BIGINT(20) NOT NULL DEFAULT 0,
            `vat_amount` BIGINT(20) NOT NULL DEFAULT 0,
            `status` ENUM('requested','pending','completed') NOT NULL DEFAULT 'requested',
            `pending_reason` TEXT DEFAULT NULL,
            `user_id` INT(11) NOT NULL,
            `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
            `deleted_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_taxinv_date` (`request_date`),
            KEY `idx_taxinv_company` (`company_id`),
            KEY `idx_taxinv_status` (`status`),
            KEY `idx_taxinv_deleted` (`is_deleted`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `tax_invoices_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
            CONSTRAINT `tax_invoices_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── tax_invoice_details (세금계산서 상세) ──
        "CREATE TABLE IF NOT EXISTS `tax_invoice_details` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tax_invoice_id` INT(11) NOT NULL,
            `product_name` VARCHAR(300) NOT NULL,
            `quantity` INT(11) NOT NULL DEFAULT 1,
            `unit_price` BIGINT(20) NOT NULL DEFAULT 0,
            `subtotal` BIGINT(20) NOT NULL DEFAULT 0,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `tax_invoice_id` (`tax_invoice_id`),
            CONSTRAINT `tax_invoice_details_ibfk_1` FOREIGN KEY (`tax_invoice_id`) REFERENCES `tax_invoices` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── audit_logs (감사 로그) ──
        "CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) DEFAULT NULL,
            `username` VARCHAR(50) DEFAULT NULL,
            `action` ENUM('INSERT','UPDATE','DELETE','LOGIN','LOGOUT','BACKUP','RESTORE','EXPORT') NOT NULL,
            `table_name` VARCHAR(50) DEFAULT NULL,
            `record_id` INT(11) DEFAULT NULL,
            `old_values` TEXT DEFAULT NULL,
            `new_values` TEXT DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_audit_user` (`user_id`),
            KEY `idx_audit_action` (`action`),
            KEY `idx_audit_date` (`created_at`),
            KEY `idx_audit_table` (`table_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── backups (백업 이력) ──
        "CREATE TABLE IF NOT EXISTS `backups` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `filename` VARCHAR(300) NOT NULL,
            `filesize` BIGINT(20) NOT NULL DEFAULT 0,
            `user_id` INT(11) NOT NULL,
            `memo` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── 기본 관리자 계정 생성 (없을 때만) ──
        "INSERT IGNORE INTO `users` (`username`, `password_hash`, `name`, `email`, `role`, `is_active`)
         VALUES ('altnet', '\$2y\$10\$dummy_hash_replace_on_first_login', 'Admin', 'admin@altnet.co.kr', 'admin', 1)",
    ],
];
