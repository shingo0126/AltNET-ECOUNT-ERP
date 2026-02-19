<?php
/**
 * sales 테이블에 delivery_date(출고일자) 컬럼 추가
 * company_id, user_id를 NULL 허용으로 변경
 * purchases 테이블의 user_id를 NULL 허용으로 변경
 * 
 * 매입 단독 등록 및 업체 미지정 매출 등록을 지원합니다.
 */
return new class {
    public function up($db) {
        // 1) delivery_date 컬럼이 없으면 추가
        $colExists = $db->fetch(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'delivery_date'"
        );
        if ((int)$colExists['cnt'] === 0) {
            $db->query("ALTER TABLE `sales` ADD COLUMN `delivery_date` DATE DEFAULT NULL AFTER `sale_date`");
        }

        // 2) company_id NULL 허용
        $db->query("ALTER TABLE `sales` MODIFY COLUMN `company_id` INT(11) DEFAULT NULL");

        // 3) sales.user_id NULL 허용
        $db->query("ALTER TABLE `sales` MODIFY COLUMN `user_id` INT(11) DEFAULT NULL");

        // 4) purchases.user_id NULL 허용
        $db->query("ALTER TABLE `purchases` MODIFY COLUMN `user_id` INT(11) DEFAULT NULL");
    }
};
