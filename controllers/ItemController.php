<?php
class ItemController {
    
    public function index() {
        $db = Database::getInstance();
        $page = max(1, (int)getParam('p', 1));
        $perPage = 25;
        
        $totalCount = $db->count('sale_items', 'is_deleted=0');
        $pag = paginate($totalCount, $perPage, $page);
        $items = $db->fetchAll(
            "SELECT * FROM sale_items WHERE is_deleted=0 ORDER BY sort_order LIMIT {$pag['per_page']} OFFSET {$pag['offset']}"
        );
        
        // Item analysis - quantity stats (SUM of sale_details.quantity per sale_item)
        $itemStats = $db->fetchAll(
            "SELECT si.id, si.sort_order, si.name, 
                    COALESCE(SUM(sd.quantity), 0) as total_quantity,
                    COALESCE(SUM(sd.subtotal), 0) as total_amount
             FROM sale_items si 
             LEFT JOIN sales s ON s.sale_item_id=si.id AND s.is_deleted=0
             LEFT JOIN sale_details sd ON sd.sale_id=s.id
             WHERE si.is_deleted=0 
             GROUP BY si.id, si.sort_order, si.name 
             ORDER BY si.sort_order"
        );
        
        // TOP 30 by quantity
        $top30Qty = $db->fetchAll(
            "SELECT si.id, si.sort_order, si.name, 
                    COALESCE(SUM(sd.quantity), 0) as total_quantity,
                    COALESCE(SUM(sd.subtotal), 0) as total_amount
             FROM sale_items si 
             LEFT JOIN sales s ON s.sale_item_id=si.id AND s.is_deleted=0
             LEFT JOIN sale_details sd ON sd.sale_id=s.id
             WHERE si.is_deleted=0 
             GROUP BY si.id, si.sort_order, si.name 
             ORDER BY total_quantity DESC
             LIMIT 30"
        );
        
        // TOP 30 by amount
        $top30Amt = $db->fetchAll(
            "SELECT si.id, si.sort_order, si.name, 
                    COALESCE(SUM(sd.quantity), 0) as total_quantity,
                    COALESCE(SUM(sd.subtotal), 0) as total_amount
             FROM sale_items si 
             LEFT JOIN sales s ON s.sale_item_id=si.id AND s.is_deleted=0
             LEFT JOIN sale_details sd ON sd.sale_id=s.id
             WHERE si.is_deleted=0 
             GROUP BY si.id, si.sort_order, si.name 
             ORDER BY total_amount DESC
             LIMIT 30"
        );
        
        // All items ranked by quantity (for detail view)
        $allByQty = $db->fetchAll(
            "SELECT si.id, si.sort_order, si.name, 
                    COALESCE(SUM(sd.quantity), 0) as total_quantity,
                    COALESCE(SUM(sd.subtotal), 0) as total_amount
             FROM sale_items si 
             LEFT JOIN sales s ON s.sale_item_id=si.id AND s.is_deleted=0
             LEFT JOIN sale_details sd ON sd.sale_id=s.id
             WHERE si.is_deleted=0 
             GROUP BY si.id, si.sort_order, si.name 
             ORDER BY total_quantity DESC"
        );
        
        // All items ranked by amount (for detail view)
        $allByAmt = $db->fetchAll(
            "SELECT si.id, si.sort_order, si.name, 
                    COALESCE(SUM(sd.quantity), 0) as total_quantity,
                    COALESCE(SUM(sd.subtotal), 0) as total_amount
             FROM sale_items si 
             LEFT JOIN sales s ON s.sale_item_id=si.id AND s.is_deleted=0
             LEFT JOIN sale_details sd ON sd.sale_id=s.id
             WHERE si.is_deleted=0 
             GROUP BY si.id, si.sort_order, si.name 
             ORDER BY total_amount DESC"
        );
        
        $pageTitle = '판매 제품 코드 관리';
        ob_start();
        include __DIR__ . '/../views/items/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('?page=items');
        if (!CSRF::verify()) redirect('?page=items');
        Auth::requireRole(['admin', 'manager']);
        
        $db = Database::getInstance();
        $id = (int)postParam('id');
        $name = trim(postParam('name', ''));
        
        if (empty($name)) {
            Session::set('flash_message', '구분명을 입력하세요.');
            Session::set('flash_type', 'danger');
            redirect('?page=items');
        }
        
        if ($id > 0) {
            $old = $db->fetch("SELECT * FROM sale_items WHERE id=?", [$id]);
            $db->update('sale_items', ['name' => $name], 'id=?', [$id]);
            AuditLog::log('UPDATE', 'sale_items', $id, $old, ['name' => $name]);
        } else {
            $maxSort = $db->fetch("SELECT MAX(sort_order) as m FROM sale_items")['m'] ?? 0;
            $sortOrder = (int)$maxSort + 1;
            $id = $db->insert('sale_items', ['sort_order' => $sortOrder, 'name' => $name]);
            AuditLog::log('INSERT', 'sale_items', $id, null, ['sort_order' => $sortOrder, 'name' => $name]);
        }
        
        Session::set('flash_message', '제품 코드가 저장되었습니다.');
        Session::set('flash_type', 'success');
        redirect('?page=items');
    }
    
    public function delete() {
        Auth::requireRole(['admin', 'manager']);
        if (!CSRF::verify($_GET['token'] ?? '')) redirect('?page=items');
        
        $db = Database::getInstance();
        $id = (int)getParam('id');
        $old = $db->fetch("SELECT * FROM sale_items WHERE id=?", [$id]);
        if ($old) {
            $db->update('sale_items', ['is_deleted' => 1], 'id=?', [$id]);
            AuditLog::log('DELETE', 'sale_items', $id, $old, null, '제품코드 삭제');
        }
        redirect('?page=items');
    }
    
    public function export() {
        $db = Database::getInstance();
        $items = $db->fetchAll("SELECT * FROM sale_items WHERE is_deleted=0 ORDER BY sort_order");
        AuditLog::log('EXPORT', 'sale_items', null, null, null, '제품코드 CSV 다운로드');
        
        csvExport('판매제품코드.csv', ['구분자', '구분'], array_map(function($i){ return [$i['sort_order'], $i['name']]; }, $items));
    }
    
    public function exportStatsQty() {
        $db = Database::getInstance();
        $stats = $db->fetchAll(
            "SELECT si.sort_order, si.name, 
                    COALESCE(SUM(sd.quantity), 0) as total_quantity,
                    COALESCE(SUM(sd.subtotal), 0) as total_amount
             FROM sale_items si 
             LEFT JOIN sales s ON s.sale_item_id=si.id AND s.is_deleted=0
             LEFT JOIN sale_details sd ON sd.sale_id=s.id
             WHERE si.is_deleted=0 
             GROUP BY si.id, si.sort_order, si.name
             ORDER BY total_quantity DESC"
        );
        AuditLog::log('EXPORT', 'sale_items', null, null, null, '제품코드 수량통계 CSV 다운로드');
        
        $rows = array_map(function($s, $i) { 
            return [$i + 1, $s['sort_order'] . '.' . $s['name'], number_format($s['total_quantity']), number_format($s['total_amount'])]; 
        }, $stats, array_keys($stats));
        csvExport('제품코드_수량통계.csv', ['순위', '제품코드', '판매수량', '매출금액'], $rows);
    }
    
    public function exportStatsAmt() {
        $db = Database::getInstance();
        $stats = $db->fetchAll(
            "SELECT si.sort_order, si.name, 
                    COALESCE(SUM(sd.quantity), 0) as total_quantity,
                    COALESCE(SUM(sd.subtotal), 0) as total_amount
             FROM sale_items si 
             LEFT JOIN sales s ON s.sale_item_id=si.id AND s.is_deleted=0
             LEFT JOIN sale_details sd ON sd.sale_id=s.id
             WHERE si.is_deleted=0 
             GROUP BY si.id, si.sort_order, si.name
             ORDER BY total_amount DESC"
        );
        AuditLog::log('EXPORT', 'sale_items', null, null, null, '제품코드 매출통계 CSV 다운로드');
        
        $rows = array_map(function($s, $i) { 
            return [$i + 1, $s['sort_order'] . '.' . $s['name'], number_format($s['total_quantity']), number_format($s['total_amount'])]; 
        }, $stats, array_keys($stats));
        csvExport('제품코드_매출통계.csv', ['순위', '제품코드', '판매수량', '매출금액'], $rows);
    }
    
    // Keep old exportStats for backward compat
    public function exportStats() {
        $this->exportStatsQty();
    }
}
