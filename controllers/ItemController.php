<?php
class ItemController {
    
    public function index() {
        $db = Database::getInstance();
        $totalCount = $db->count('sale_items', 'is_deleted=0');
        $items = $db->fetchAll("SELECT * FROM sale_items WHERE is_deleted=0 ORDER BY sort_order");
        
        // Item analysis - usage stats
        $itemStats = $db->fetchAll(
            "SELECT si.id, si.sort_order, si.name, COUNT(s.id) as usage_count, COALESCE(SUM(s.total_amount),0) as total_amount
             FROM sale_items si 
             LEFT JOIN sales s ON s.sale_item_id=si.id AND s.is_deleted=0
             WHERE si.is_deleted=0 
             GROUP BY si.id, si.sort_order, si.name 
             ORDER BY usage_count DESC"
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
            // Auto sort_order
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
    
    public function exportStats() {
        $db = Database::getInstance();
        $stats = $db->fetchAll(
            "SELECT si.sort_order, si.name, COUNT(s.id) as cnt, COALESCE(SUM(s.total_amount),0) as total
             FROM sale_items si LEFT JOIN sales s ON s.sale_item_id=si.id AND s.is_deleted=0
             WHERE si.is_deleted=0 GROUP BY si.id ORDER BY cnt DESC"
        );
        AuditLog::log('EXPORT', 'sale_items', null, null, null, '제품코드 통계 CSV 다운로드');
        
        $rows = array_map(function($s) { return [$s['sort_order'] . '.' . $s['name'], $s['cnt'], number_format($s['total'])]; }, $stats);
        csvExport('제품코드통계.csv', ['제품코드', '사용횟수', '매출총액'], $rows);
    }
}
