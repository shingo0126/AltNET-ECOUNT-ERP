<?php
class AuditController {
    
    public function index() {
        $db = Database::getInstance();
        $page = max(1, (int)getParam('p', 1));
        $perPage = 30;
        $filterUser = getParam('user', '');
        $filterAction = getParam('act', '');
        $filterDateFrom = getParam('from', '');
        $filterDateTo = getParam('to', '');
        
        $where = "1=1";
        $params = [];
        
        if ($filterUser) {
            $where .= " AND a.username LIKE ?";
            $params[] = "%{$filterUser}%";
        }
        if ($filterAction) {
            $where .= " AND a.action = ?";
            $params[] = $filterAction;
        }
        if ($filterDateFrom) {
            $where .= " AND DATE(a.created_at) >= ?";
            $params[] = $filterDateFrom;
        }
        if ($filterDateTo) {
            $where .= " AND DATE(a.created_at) <= ?";
            $params[] = $filterDateTo;
        }
        
        $total = $db->fetch("SELECT COUNT(*) as cnt FROM audit_logs a WHERE $where", $params)['cnt'];
        $pag = paginate($total, $perPage, $page);
        
        $logs = $db->fetchAll(
            "SELECT a.* FROM audit_logs a WHERE $where ORDER BY a.created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
            $params
        );
        
        $pageTitle = '감사 로그';
        ob_start();
        include __DIR__ . '/../views/audit/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    public function export() {
        $db = Database::getInstance();
        $logs = $db->fetchAll("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10000");
        
        AuditLog::log('EXPORT', 'audit_logs', null, null, null, '감사로그 CSV 다운로드');
        
        $headers = ['일시', '사용자', '액션', '테이블', 'ID', '설명', 'IP'];
        $rows = array_map(function($l) {
            return [$l['created_at'], $l['username'], $l['action'], $l['table_name'], $l['record_id'], $l['description'], $l['ip_address']];
        }, $logs);
        
        csvExport('감사로그.csv', $headers, $rows);
    }
}
