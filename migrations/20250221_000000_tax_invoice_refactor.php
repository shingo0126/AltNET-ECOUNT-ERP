<?php
/**
 * 세금계산서 리팩토링 마이그레이션
 * - tax_invoices: sale_number, delivery_date, linked_sale_id 추가
 * - tax_invoice_details: sale_item_id 추가
 * - tax_invoice_purchases 신규 테이블
 * - tax_invoice_purchase_details 신규 테이블
 */
return [
    'up' => [
        // 1. tax_invoices 확장
        "ALTER TABLE tax_invoices ADD COLUMN sale_number VARCHAR(20) NULL AFTER id",
        "ALTER TABLE tax_invoices ADD COLUMN delivery_date DATE NULL AFTER request_date",
        "ALTER TABLE tax_invoices ADD COLUMN linked_sale_id INT NULL AFTER pending_reason",
        "ALTER TABLE tax_invoices ADD COLUMN purchase_total_amount BIGINT NOT NULL DEFAULT 0 AFTER vat_amount",
        "ALTER TABLE tax_invoices ADD COLUMN purchase_vat_amount BIGINT NOT NULL DEFAULT 0 AFTER purchase_total_amount",
        
        // 2. tax_invoice_details에 sale_item_id 추가
        "ALTER TABLE tax_invoice_details ADD COLUMN sale_item_id INT NULL AFTER product_name",
        
        // 3. 매입 마스터 테이블
        "CREATE TABLE IF NOT EXISTS tax_invoice_purchases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tax_invoice_id INT NOT NULL,
            purchase_date DATE NOT NULL,
            total_amount BIGINT NOT NULL DEFAULT 0,
            vat_amount BIGINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tax_invoice_id) REFERENCES tax_invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 4. 매입 상세 테이블
        "CREATE TABLE IF NOT EXISTS tax_invoice_purchase_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tax_invoice_purchase_id INT NOT NULL,
            product_name VARCHAR(300) NOT NULL,
            vendor_id INT NULL,
            unit_price BIGINT NOT NULL DEFAULT 0,
            quantity INT NOT NULL DEFAULT 1,
            subtotal BIGINT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tax_invoice_purchase_id) REFERENCES tax_invoice_purchases(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 5. 인덱스
        "CREATE INDEX idx_ti_purchases_invoice ON tax_invoice_purchases(tax_invoice_id)",
        "CREATE INDEX idx_ti_purch_details_purchase ON tax_invoice_purchase_details(tax_invoice_purchase_id)",
    ],
];
