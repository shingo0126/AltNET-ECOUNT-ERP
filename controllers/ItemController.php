<?php
class ItemController {
    
    /**
     * 기간 필터 WHERE 조건 빌드
     */
    private function buildDateFilter($year, $view, $month, $quarter) {
        $where = '';
        $params = [];
        if ($year) {
            $where .= " AND YEAR(s.sale_date)=?";
            $params[] = $year;
        }
        if ($view === 'quarterly' && $quarter) {
            $where .= " AND QUARTER(s.sale_date)=?";
            $params[] = $quarter;
        } elseif ($view === 'monthly' && $month) {
            $where .= " AND MONTH(s.sale_date)=?";
            $params[] = $month;
        }
        return ['where' => $where, 'params' => $params];
    }
    
    /**
     * 기간 라벨 생성
     */
    private function buildPeriodLabel($year, $view, $month, $quarter) {
        if (!$year) return '전체 기간';
        if ($view === 'quarterly') {
            $qNames = ['', '1분기(1~3월)', '2분기(4~6월)', '3분기(7~9월)', '4분기(10~12월)'];
            return "{$year}년 " . ($qNames[(int)$quarter] ?? "{$quarter}분기");
        } elseif ($view === 'monthly') {
            return "{$year}년 {$month}월";
        }
        return "{$year}년 전체";
    }
    
    /**
     * 기간 필터 적용된 집계 쿼리 실행
     */
    private function fetchFilteredStats($db, $dateFilter, $orderBy, $limit = null) {
        $limitSql = $limit ? " LIMIT {$limit}" : '';
        return $db->fetchAll(
            "SELECT si.id, si.sort_order, si.name, 
                    COALESCE(SUM(sd.quantity), 0) as total_quantity,
                    COALESCE(SUM(sd.subtotal), 0) as total_amount
             FROM sale_items si 
             LEFT JOIN sale_details sd ON sd.sale_item_id=si.id
             LEFT JOIN sales s ON s.id=sd.sale_id AND s.is_deleted=0 {$dateFilter['where']}
             WHERE si.is_deleted=0 AND (sd.id IS NULL OR s.id IS NOT NULL)
             GROUP BY si.id, si.sort_order, si.name 
             ORDER BY {$orderBy}{$limitSql}",
            $dateFilter['params']
        );
    }
    
    public function index() {
        $db = Database::getInstance();
        $page = max(1, (int)getParam('p', 1));
        $perPage = 25;
        
        $totalCount = $db->count('sale_items', 'is_deleted=0');
        $pag = paginate($totalCount, $perPage, $page);
        $items = $db->fetchAll(
            "SELECT * FROM sale_items WHERE is_deleted=0 ORDER BY sort_order LIMIT {$pag['per_page']} OFFSET {$pag['offset']}"
        );
        
        // ===== TOP15 필터 파라미터 =====
        $statsYear = getParam('stats_year', date('Y'));
        $statsView = getParam('stats_view', 'yearly');
        $statsMonth = getParam('stats_month', date('m'));
        $statsQuarter = getParam('stats_quarter', '1');
        $statsPeriodLabel = $this->buildPeriodLabel($statsYear, $statsView, $statsMonth, $statsQuarter);
        
        $dateFilter = $this->buildDateFilter($statsYear, $statsView, $statsMonth, $statsQuarter);
        
        // Item stats (for table row display)
        $itemStats = $db->fetchAll(
            "SELECT si.id, si.sort_order, si.name, 
                    COALESCE(SUM(sd.quantity), 0) as total_quantity,
                    COALESCE(SUM(sd.subtotal), 0) as total_amount
             FROM sale_items si 
             LEFT JOIN sale_details sd ON sd.sale_item_id=si.id
             LEFT JOIN sales s ON s.id=sd.sale_id AND s.is_deleted=0
             WHERE si.is_deleted=0 AND (sd.id IS NULL OR s.id IS NOT NULL)
             GROUP BY si.id, si.sort_order, si.name 
             ORDER BY si.sort_order"
        );
        
        $top15Qty = $this->fetchFilteredStats($db, $dateFilter, 'total_quantity DESC', 15);
        $top15Amt = $this->fetchFilteredStats($db, $dateFilter, 'total_amount DESC', 15);
        $allByQty = $this->fetchFilteredStats($db, $dateFilter, 'total_quantity DESC');
        $allByAmt = $this->fetchFilteredStats($db, $dateFilter, 'total_amount DESC');
        
        // Available years
        $currentYear = (int)date('Y');
        $yearsFromDb = $db->fetchAll(
            "SELECT DISTINCT YEAR(s.sale_date) as y FROM sales s WHERE s.is_deleted=0"
        );
        $yearSet = [];
        foreach ($yearsFromDb as $row) { $yearSet[(int)$row['y']] = true; }
        for ($y = $currentYear - 3; $y <= $currentYear + 1; $y++) { $yearSet[$y] = true; }
        krsort($yearSet);
        $statsYears = [];
        foreach ($yearSet as $y => $v) { $statsYears[] = ['y' => $y]; }
        
        $pageTitle = '판매 제품 코드 관리';
        ob_start();
        include __DIR__ . '/../views/items/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    /**
     * AJAX API: 필터 적용된 TOP15 데이터 반환
     * GET ?page=items&action=itemStatsData&stats_year=2026&stats_view=quarterly&stats_quarter=1
     */
    public function itemStatsData() {
        $db = Database::getInstance();
        $statsYear = getParam('stats_year', date('Y'));
        $statsView = getParam('stats_view', 'yearly');
        $statsMonth = getParam('stats_month', date('m'));
        $statsQuarter = getParam('stats_quarter', '1');
        
        $dateFilter = $this->buildDateFilter($statsYear, $statsView, $statsMonth, $statsQuarter);
        $periodLabel = $this->buildPeriodLabel($statsYear, $statsView, $statsMonth, $statsQuarter);
        
        $top15Qty = $this->fetchFilteredStats($db, $dateFilter, 'total_quantity DESC', 15);
        $top15Amt = $this->fetchFilteredStats($db, $dateFilter, 'total_amount DESC', 15);
        $allByQty = $this->fetchFilteredStats($db, $dateFilter, 'total_quantity DESC');
        $allByAmt = $this->fetchFilteredStats($db, $dateFilter, 'total_amount DESC');
        
        jsonResponse([
            'periodLabel' => $periodLabel,
            'top15Qty' => array_map(function($r) { return ['code' => $r['sort_order'].'.'.$r['name'], 'qty' => (int)$r['total_quantity'], 'amt' => (int)$r['total_amount']]; }, $top15Qty),
            'top15Amt' => array_map(function($r) { return ['code' => $r['sort_order'].'.'.$r['name'], 'qty' => (int)$r['total_quantity'], 'amt' => (int)$r['total_amount']]; }, $top15Amt),
            'allByQty' => array_map(function($r) { return ['code' => $r['sort_order'].'.'.$r['name'], 'qty' => (int)$r['total_quantity'], 'amt' => (int)$r['total_amount']]; }, $allByQty),
            'allByAmt' => array_map(function($r) { return ['code' => $r['sort_order'].'.'.$r['name'], 'qty' => (int)$r['total_quantity'], 'amt' => (int)$r['total_amount']]; }, $allByAmt)
        ]);
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
        $statsYear = getParam('stats_year', '');
        $statsView = getParam('stats_view', 'yearly');
        $statsMonth = getParam('stats_month', date('m'));
        $statsQuarter = getParam('stats_quarter', '1');
        $dateFilter = $this->buildDateFilter($statsYear, $statsView, $statsMonth, $statsQuarter);
        $periodLabel = $statsYear ? $this->buildPeriodLabel($statsYear, $statsView, $statsMonth, $statsQuarter) : '전체 기간';
        
        $stats = $this->fetchFilteredStats($db, $dateFilter, 'total_quantity DESC');
        AuditLog::log('EXPORT', 'sale_items', null, null, null, "제품코드 수량통계 CSV 다운로드 ({$periodLabel})");
        
        $rows = [];
        $rows[] = ['조회기간', $periodLabel, '', ''];
        $rows[] = ['', '', '', ''];
        foreach ($stats as $i => $s) {
            $rows[] = [$i + 1, $s['sort_order'] . '.' . $s['name'], number_format($s['total_quantity']), number_format($s['total_amount'])];
        }
        $suffix = $statsYear ? "_{$statsYear}" : '';
        csvExport("제품코드_수량통계{$suffix}.csv", ['순위', '제품코드', '판매수량', '매출금액'], $rows);
    }
    
    public function exportStatsAmt() {
        $db = Database::getInstance();
        $statsYear = getParam('stats_year', '');
        $statsView = getParam('stats_view', 'yearly');
        $statsMonth = getParam('stats_month', date('m'));
        $statsQuarter = getParam('stats_quarter', '1');
        $dateFilter = $this->buildDateFilter($statsYear, $statsView, $statsMonth, $statsQuarter);
        $periodLabel = $statsYear ? $this->buildPeriodLabel($statsYear, $statsView, $statsMonth, $statsQuarter) : '전체 기간';
        
        $stats = $this->fetchFilteredStats($db, $dateFilter, 'total_amount DESC');
        AuditLog::log('EXPORT', 'sale_items', null, null, null, "제품코드 매출통계 CSV 다운로드 ({$periodLabel})");
        
        $rows = [];
        $rows[] = ['조회기간', $periodLabel, '', ''];
        $rows[] = ['', '', '', ''];
        foreach ($stats as $i => $s) {
            $rows[] = [$i + 1, $s['sort_order'] . '.' . $s['name'], number_format($s['total_quantity']), number_format($s['total_amount'])];
        }
        $suffix = $statsYear ? "_{$statsYear}" : '';
        csvExport("제품코드_매출통계{$suffix}.csv", ['순위', '제품코드', '판매수량', '매출금액'], $rows);
    }
    
    // Keep old exportStats for backward compat
    public function exportStats() {
        $this->exportStatsQty();
    }
}
