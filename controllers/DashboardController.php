<?php
class DashboardController {
    
    public function index() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '');
        $viewType = getParam('view', 'monthly'); // monthly or quarterly
        
        // === TOP20 독립 필터 파라미터 ===
        $topYear = getParam('top_year', $year);
        $topView = getParam('top_view', 'yearly');  // yearly, quarterly, monthly
        $topMonth = getParam('top_month', date('m'));
        $topQuarter = getParam('top_quarter', '1');
        
        // Build date filter for summary period
        if ($viewType === 'quarterly' && $quarter) {
            $periodLabel = "{$year}년 {$quarter}분기";
        } else {
            $periodLabel = "{$year}년 {$month}월";
        }
        
        // Monthly sales total
        if ($viewType === 'quarterly' && $quarter) {
            $monthlySales = $db->fetch(
                "SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt 
                 FROM sales WHERE YEAR(sale_date)=? AND QUARTER(sale_date)=? AND is_deleted=0",
                [$year, $quarter]
            );
            $monthlyPurchases = $db->fetch(
                "SELECT COALESCE(SUM(total_amount),0) as total 
                 FROM purchases WHERE YEAR(purchase_date)=? AND QUARTER(purchase_date)=? AND is_deleted=0",
                [$year, $quarter]
            );
        } else {
            $monthlySales = $db->fetch(
                "SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt 
                 FROM sales WHERE YEAR(sale_date)=? AND MONTH(sale_date)=? AND is_deleted=0",
                [$year, $month]
            );
            $monthlyPurchases = $db->fetch(
                "SELECT COALESCE(SUM(total_amount),0) as total 
                 FROM purchases WHERE YEAR(purchase_date)=? AND MONTH(purchase_date)=? AND is_deleted=0",
                [$year, $month]
            );
        }
        
        // Monthly data for chart (all 12 months)
        $monthlyData = $db->fetchAll(
            "SELECT MONTH(sale_date) as m, COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt
             FROM sales WHERE YEAR(sale_date)=? AND is_deleted=0 GROUP BY MONTH(sale_date) ORDER BY m",
            [$year]
        );
        
        // Quarterly data
        $quarterlyData = $db->fetchAll(
            "SELECT QUARTER(sale_date) as q, COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt
             FROM sales WHERE YEAR(sale_date)=? AND is_deleted=0 GROUP BY QUARTER(sale_date) ORDER BY q",
            [$year]
        );
        
        // Monthly registration counts
        $monthlyCountData = $db->fetchAll(
            "SELECT MONTH(sale_date) as m, COUNT(*) as cnt
             FROM sales WHERE YEAR(sale_date)=? AND is_deleted=0 GROUP BY MONTH(sale_date) ORDER BY m",
            [$year]
        );
        
        // === TOP20 독립 필터를 사용한 쿼리 ===
        $topResult = $this->buildTopData($db, $topYear, $topView, $topMonth, $topQuarter);
        $topCompanies = $topResult['topCompanies'];
        $allCompanies = $topResult['allCompanies'];
        $topVendors = $topResult['topVendors'];
        $allVendors = $topResult['allVendors'];
        $topPeriodLabel = $topResult['periodLabel'];
        
        // Available years
        $years = $db->fetchAll(
            "SELECT DISTINCT YEAR(sale_date) as y FROM sales WHERE is_deleted=0 
             UNION SELECT YEAR(CURDATE()) ORDER BY y DESC"
        );
        
        $pageTitle = '대시보드';
        
        // Prepare chart data
        $chartMonthly = array_fill(1, 12, 0);
        foreach ($monthlyData as $row) { $chartMonthly[(int)$row['m']] = (int)$row['total']; }
        
        $chartQuarterly = array_fill(1, 4, 0);
        foreach ($quarterlyData as $row) { $chartQuarterly[(int)$row['q']] = (int)$row['total']; }
        
        $chartCounts = array_fill(1, 12, 0);
        foreach ($monthlyCountData as $row) { $chartCounts[(int)$row['m']] = (int)$row['cnt']; }
        
        ob_start();
        include __DIR__ . '/../views/dashboard/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    /**
     * TOP20 데이터 빌드 (독립 필터 기준)
     */
    private function buildTopData($db, $topYear, $topView, $topMonth, $topQuarter) {
        // 매출 업체 WHERE
        $companyWhere = "s.is_deleted=0 AND YEAR(s.sale_date)=?";
        $companyParams = [$topYear];
        
        // 매입 업체 WHERE
        $vendorWhere = "p.is_deleted=0 AND YEAR(p.purchase_date)=?";
        $vendorParams = [$topYear];
        
        // 기간 레이블
        if ($topView === 'quarterly') {
            $companyWhere .= " AND QUARTER(s.sale_date)=?";
            $companyParams[] = $topQuarter;
            $vendorWhere .= " AND QUARTER(p.purchase_date)=?";
            $vendorParams[] = $topQuarter;
            $qNames = ['', '1분기(1~3월)', '2분기(4~6월)', '3분기(7~9월)', '4분기(10~12월)'];
            $periodLabel = "{$topYear}년 " . ($qNames[(int)$topQuarter] ?? "{$topQuarter}분기");
        } elseif ($topView === 'monthly') {
            $companyWhere .= " AND MONTH(s.sale_date)=?";
            $companyParams[] = $topMonth;
            $vendorWhere .= " AND MONTH(p.purchase_date)=?";
            $vendorParams[] = $topMonth;
            $periodLabel = "{$topYear}년 {$topMonth}월";
        } else {
            // yearly - 년도 전체
            $periodLabel = "{$topYear}년 전체";
        }
        
        // Top 20 매출 업체
        $topCompanies = $db->fetchAll(
            "SELECT c.name, SUM(s.total_amount) as total 
             FROM sales s JOIN companies c ON s.company_id=c.id 
             WHERE $companyWhere
             GROUP BY c.id, c.name ORDER BY total DESC LIMIT 20",
            $companyParams
        );
        
        // 전체 매출 업체
        $allCompanies = $db->fetchAll(
            "SELECT c.name, SUM(s.total_amount) as total 
             FROM sales s JOIN companies c ON s.company_id=c.id 
             WHERE $companyWhere
             GROUP BY c.id, c.name ORDER BY total DESC",
            $companyParams
        );
        
        // Top 20 매입 업체
        $topVendors = $db->fetchAll(
            "SELECT v.name, SUM(p.total_amount) as total 
             FROM purchases p JOIN vendors v ON p.vendor_id=v.id 
             WHERE $vendorWhere
             GROUP BY v.id, v.name ORDER BY total DESC LIMIT 20",
            $vendorParams
        );
        
        // 전체 매입 업체
        $allVendors = $db->fetchAll(
            "SELECT v.name, SUM(p.total_amount) as total 
             FROM purchases p JOIN vendors v ON p.vendor_id=v.id 
             WHERE $vendorWhere
             GROUP BY v.id, v.name ORDER BY total DESC",
            $vendorParams
        );
        
        return compact('topCompanies', 'allCompanies', 'topVendors', 'allVendors', 'periodLabel');
    }
    
    /**
     * TOP20 JSON API (AJAX용)
     */
    public function topData() {
        $db = Database::getInstance();
        $topYear = getParam('top_year', date('Y'));
        $topView = getParam('top_view', 'yearly');
        $topMonth = getParam('top_month', date('m'));
        $topQuarter = getParam('top_quarter', '1');
        
        $result = $this->buildTopData($db, $topYear, $topView, $topMonth, $topQuarter);
        
        jsonResponse([
            'companyNames'  => array_column($result['topCompanies'], 'name'),
            'companyTotals' => array_map('intval', array_column($result['topCompanies'], 'total')),
            'vendorNames'   => array_column($result['topVendors'], 'name'),
            'vendorTotals'  => array_map('intval', array_column($result['topVendors'], 'total')),
            'allCompanies'  => $result['allCompanies'],
            'allVendors'    => $result['allVendors'],
            'periodLabel'   => $result['periodLabel'],
        ]);
    }
    
    /**
     * 매출업체 CSV 내보내기
     */
    public function exportCompanies() {
        $db = Database::getInstance();
        $topYear = getParam('top_year', getParam('year', date('Y')));
        $topView = getParam('top_view', 'yearly');
        $topMonth = getParam('top_month', getParam('month', date('m')));
        $topQuarter = getParam('top_quarter', getParam('quarter', '1'));
        
        $result = $this->buildTopData($db, $topYear, $topView, $topMonth, $topQuarter);
        $data = $result['allCompanies'];
        $periodLabel = $result['periodLabel'];
        
        // 파일명 및 내부 레이블 생성
        $fileInfo = $this->buildExportLabel('매출업체순위', $topYear, $topView, $topMonth, $topQuarter);
        
        AuditLog::log('EXPORT', 'companies', null, null, null, "매출업체 순위 CSV 다운로드 ({$periodLabel})");
        
        // CSV 헤더에 기간 정보를 포함
        $rows = [];
        // 첫 행: 기간 정보
        $rows[] = ['조회기간', $periodLabel, ''];
        $rows[] = ['', '', ''];  // 빈 줄
        // 데이터 행
        foreach ($data as $i => $d) {
            $rows[] = [$i + 1, $d['name'], number_format($d['total'])];
        }
        // 합계
        $grandTotal = array_sum(array_column($data, 'total'));
        $rows[] = ['', '', ''];
        $rows[] = ['합계', count($data) . '개사', number_format($grandTotal)];
        
        csvExport($fileInfo['filename'], ['순위', '업체명', '매출총액(원)'], $rows);
    }
    
    /**
     * 매입업체 CSV 내보내기
     */
    public function exportVendors() {
        $db = Database::getInstance();
        $topYear = getParam('top_year', getParam('year', date('Y')));
        $topView = getParam('top_view', 'yearly');
        $topMonth = getParam('top_month', getParam('month', date('m')));
        $topQuarter = getParam('top_quarter', getParam('quarter', '1'));
        
        $result = $this->buildTopData($db, $topYear, $topView, $topMonth, $topQuarter);
        $data = $result['allVendors'];
        $periodLabel = $result['periodLabel'];
        
        $fileInfo = $this->buildExportLabel('매입업체순위', $topYear, $topView, $topMonth, $topQuarter);
        
        AuditLog::log('EXPORT', 'vendors', null, null, null, "매입업체 순위 CSV 다운로드 ({$periodLabel})");
        
        $rows = [];
        $rows[] = ['조회기간', $periodLabel, ''];
        $rows[] = ['', '', ''];
        foreach ($data as $i => $d) {
            $rows[] = [$i + 1, $d['name'], number_format($d['total'])];
        }
        $grandTotal = array_sum(array_column($data, 'total'));
        $rows[] = ['', '', ''];
        $rows[] = ['합계', count($data) . '개사', number_format($grandTotal)];
        
        csvExport($fileInfo['filename'], ['순위', '업체명', '매입총액(원)'], $rows);
    }
    
    /**
     * 내보내기 레이블/파일명 생성
     */
    private function buildExportLabel($prefix, $topYear, $topView, $topMonth, $topQuarter) {
        if ($topView === 'quarterly') {
            $qNames = ['', '1Q', '2Q', '3Q', '4Q'];
            $suffix = "{$topYear}_{$qNames[(int)$topQuarter]}";
            $label = "{$topYear}년 {$topQuarter}분기";
        } elseif ($topView === 'monthly') {
            $mm = str_pad($topMonth, 2, '0', STR_PAD_LEFT);
            $suffix = "{$topYear}_{$mm}";
            $label = "{$topYear}년 {$topMonth}월";
        } else {
            $suffix = "{$topYear}_전체";
            $label = "{$topYear}년 전체";
        }
        
        return [
            'filename' => "{$prefix}_{$suffix}.csv",
            'label'    => $label,
        ];
    }
}
