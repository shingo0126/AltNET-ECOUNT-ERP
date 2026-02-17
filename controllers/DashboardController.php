<?php
class DashboardController {
    
    public function index() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '1'); // 기본값 1분기 (빈 문자열 방지)
        $viewType = getParam('view', 'monthly'); // monthly, quarterly, yearly
        
        // === TOP20 독립 필터 파라미터 ===
        $topYear = getParam('top_year', $year);
        $topView = getParam('top_view', 'yearly');
        $topMonth = getParam('top_month', date('m'));
        $topQuarter = getParam('top_quarter', '1');
        
        // Build date filter for summary period
        if ($viewType === 'yearly') {
            $periodLabel = "{$year}년 전체";
        } elseif ($viewType === 'quarterly' && $quarter) {
            $qNames = ['', '1분기(1~3월)', '2분기(4~6월)', '3분기(7~9월)', '4분기(10~12월)'];
            $periodLabel = "{$year}년 " . ($qNames[(int)$quarter] ?? "{$quarter}분기");
        } else {
            $periodLabel = "{$year}년 {$month}월";
        }
        
        // Summary stats for period
        if ($viewType === 'yearly') {
            $monthlySales = $db->fetch(
                "SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt 
                 FROM sales WHERE YEAR(sale_date)=? AND is_deleted=0",
                [$year]
            );
            $monthlyPurchases = $db->fetch(
                "SELECT COALESCE(SUM(total_amount),0) as total 
                 FROM purchases WHERE YEAR(purchase_date)=? AND is_deleted=0",
                [$year]
            );
        } elseif ($viewType === 'quarterly' && $quarter) {
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
        
        // ===== 월별 매출 데이터 (12개월) =====
        $monthlyData = $db->fetchAll(
            "SELECT MONTH(sale_date) as m, COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt
             FROM sales WHERE YEAR(sale_date)=? AND is_deleted=0 GROUP BY MONTH(sale_date) ORDER BY m",
            [$year]
        );
        
        // ===== 월별 매입 데이터 (12개월) - 신규 추가 =====
        $monthlyPurchaseData = $db->fetchAll(
            "SELECT MONTH(purchase_date) as m, COALESCE(SUM(total_amount),0) as total
             FROM purchases WHERE YEAR(purchase_date)=? AND is_deleted=0 GROUP BY MONTH(purchase_date) ORDER BY m",
            [$year]
        );
        
        // ===== 분기별 매출 데이터 =====
        $quarterlyData = $db->fetchAll(
            "SELECT QUARTER(sale_date) as q, COALESCE(SUM(total_amount),0) as total
             FROM sales WHERE YEAR(sale_date)=? AND is_deleted=0 GROUP BY QUARTER(sale_date) ORDER BY q",
            [$year]
        );
        
        // ===== 분기별 매입 데이터 - 신규 추가 =====
        $quarterlyPurchaseData = $db->fetchAll(
            "SELECT QUARTER(purchase_date) as q, COALESCE(SUM(total_amount),0) as total
             FROM purchases WHERE YEAR(purchase_date)=? AND is_deleted=0 GROUP BY QUARTER(purchase_date) ORDER BY q",
            [$year]
        );
        
        // ===== 월별 등록 건수 =====
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
        
        // ===== Available years (과거 5년 ~ 현재+1년) =====
        $currentYear = (int)date('Y');
        $yearsFromDb = $db->fetchAll(
            "SELECT DISTINCT YEAR(sale_date) as y FROM sales WHERE is_deleted=0 
             UNION SELECT DISTINCT YEAR(purchase_date) as y FROM purchases WHERE is_deleted=0"
        );
        $yearSet = [];
        foreach ($yearsFromDb as $row) { $yearSet[(int)$row['y']] = true; }
        for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++) { $yearSet[$y] = true; }
        krsort($yearSet);
        $years = [];
        foreach ($yearSet as $y => $v) { $years[] = ['y' => $y]; }
        
        // ===== 세금계산서 발행 요청 최근 5건 (요청 건만, 보류/완료 제외) =====
        $recentTaxInvoices = $db->fetchAll(
            "SELECT t.id, t.request_date, t.project_name, t.total_amount, t.status, t.created_at,
                    c.name as company_name
             FROM tax_invoices t
             LEFT JOIN companies c ON t.company_id=c.id
             WHERE t.is_deleted=0 AND t.status='requested'
             ORDER BY t.created_at DESC
             LIMIT 5"
        );
        
        // ===== 전년도 대비 매출 분석 초기 데이터 (현재년도 + 전년도) =====
        $compareStartYear = $currentYear - 1;
        $compareData = [];
        for ($cy = $compareStartYear; $cy <= $currentYear; $cy++) {
            $cMonthly = array_fill(1, 12, 0);
            $cRows = $db->fetchAll(
                "SELECT MONTH(sale_date) as m, COALESCE(SUM(total_amount),0) as total
                 FROM sales WHERE YEAR(sale_date)=? AND is_deleted=0 GROUP BY MONTH(sale_date) ORDER BY m",
                [$cy]
            );
            foreach ($cRows as $cr) { $cMonthly[(int)$cr['m']] = (int)$cr['total']; }
            $compareData[(string)$cy] = array_values($cMonthly);
        }
        
        $pageTitle = '대시보드';
        
        // Prepare chart data - 매출
        $chartMonthly = array_fill(1, 12, 0);
        foreach ($monthlyData as $row) { $chartMonthly[(int)$row['m']] = (int)$row['total']; }
        
        $chartQuarterly = array_fill(1, 4, 0);
        foreach ($quarterlyData as $row) { $chartQuarterly[(int)$row['q']] = (int)$row['total']; }
        
        // Prepare chart data - 매입 (신규)
        $chartMonthlyPurchase = array_fill(1, 12, 0);
        foreach ($monthlyPurchaseData as $row) { $chartMonthlyPurchase[(int)$row['m']] = (int)$row['total']; }
        
        $chartQuarterlyPurchase = array_fill(1, 4, 0);
        foreach ($quarterlyPurchaseData as $row) { $chartQuarterlyPurchase[(int)$row['q']] = (int)$row['total']; }
        
        // 등록 건수
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
        $companyWhere = "s.is_deleted=0 AND YEAR(s.sale_date)=?";
        $companyParams = [$topYear];
        $vendorWhere = "p.is_deleted=0 AND YEAR(p.purchase_date)=?";
        $vendorParams = [$topYear];
        
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
            $periodLabel = "{$topYear}년 전체";
        }
        
        $topCompanies = $db->fetchAll(
            "SELECT c.name, SUM(s.total_amount) as total FROM sales s JOIN companies c ON s.company_id=c.id 
             WHERE $companyWhere GROUP BY c.id, c.name ORDER BY total DESC LIMIT 20", $companyParams);
        $allCompanies = $db->fetchAll(
            "SELECT c.name, SUM(s.total_amount) as total FROM sales s JOIN companies c ON s.company_id=c.id 
             WHERE $companyWhere GROUP BY c.id, c.name ORDER BY total DESC", $companyParams);
        $topVendors = $db->fetchAll(
            "SELECT v.name, SUM(pd.subtotal) as total 
             FROM purchase_details pd
             JOIN purchases p ON pd.purchase_id = p.id
             JOIN vendors v ON COALESCE(pd.vendor_id, p.vendor_id) = v.id 
             WHERE $vendorWhere GROUP BY v.id, v.name ORDER BY total DESC LIMIT 20", $vendorParams);
        $allVendors = $db->fetchAll(
            "SELECT v.name, SUM(pd.subtotal) as total 
             FROM purchase_details pd
             JOIN purchases p ON pd.purchase_id = p.id
             JOIN vendors v ON COALESCE(pd.vendor_id, p.vendor_id) = v.id 
             WHERE $vendorWhere GROUP BY v.id, v.name ORDER BY total DESC", $vendorParams);
        
        return compact('topCompanies', 'allCompanies', 'topVendors', 'allVendors', 'periodLabel');
    }
    
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
    
    public function exportCompanies() {
        $db = Database::getInstance();
        $topYear = getParam('top_year', getParam('year', date('Y')));
        $topView = getParam('top_view', 'yearly');
        $topMonth = getParam('top_month', getParam('month', date('m')));
        $topQuarter = getParam('top_quarter', getParam('quarter', '1'));
        
        $result = $this->buildTopData($db, $topYear, $topView, $topMonth, $topQuarter);
        $data = $result['allCompanies'];
        $periodLabel = $result['periodLabel'];
        $fileInfo = $this->buildExportLabel('매출업체순위', $topYear, $topView, $topMonth, $topQuarter);
        
        AuditLog::log('EXPORT', 'companies', null, null, null, "매출업체 순위 CSV 다운로드 ({$periodLabel})");
        
        $rows = [];
        $rows[] = ['조회기간', $periodLabel, ''];
        $rows[] = ['', '', ''];
        foreach ($data as $i => $d) { $rows[] = [$i + 1, $d['name'], number_format($d['total'])]; }
        $grandTotal = array_sum(array_column($data, 'total'));
        $rows[] = ['', '', ''];
        $rows[] = ['합계', count($data) . '개사', number_format($grandTotal)];
        csvExport($fileInfo['filename'], ['순위', '업체명', '매출총액(원)'], $rows);
    }
    
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
        foreach ($data as $i => $d) { $rows[] = [$i + 1, $d['name'], number_format($d['total'])]; }
        $grandTotal = array_sum(array_column($data, 'total'));
        $rows[] = ['', '', ''];
        $rows[] = ['합계', count($data) . '개사', number_format($grandTotal)];
        csvExport($fileInfo['filename'], ['순위', '업체명', '매입총액(원)'], $rows);
    }
    
    private function buildExportLabel($prefix, $topYear, $topView, $topMonth, $topQuarter) {
        if ($topView === 'quarterly') {
            $qNames = ['', '1Q', '2Q', '3Q', '4Q'];
            $suffix = "{$topYear}_{$qNames[(int)$topQuarter]}";
        } elseif ($topView === 'monthly') {
            $mm = str_pad($topMonth, 2, '0', STR_PAD_LEFT);
            $suffix = "{$topYear}_{$mm}";
        } else {
            $suffix = "{$topYear}_전체";
        }
        return ['filename' => "{$prefix}_{$suffix}.csv"];
    }
    
    /**
     * 전년도 대비 매출 분석 AJAX API
     * GET ?page=dashboard&action=yearlyComparison&start_year=2025
     * 응답: { years: { "2025": [0,9000000,...], "2026": [...] }, currentYear: 2026 }
     */
    public function yearlyComparison() {
        $db = Database::getInstance();
        $currentYear = (int)date('Y');
        $startYear = max(2020, (int)getParam('start_year', $currentYear - 1));
        
        $result = [];
        for ($y = $startYear; $y <= $currentYear; $y++) {
            $monthly = array_fill(1, 12, 0);
            $rows = $db->fetchAll(
                "SELECT MONTH(sale_date) as m, COALESCE(SUM(total_amount),0) as total
                 FROM sales WHERE YEAR(sale_date)=? AND is_deleted=0 
                 GROUP BY MONTH(sale_date) ORDER BY m",
                [$y]
            );
            foreach ($rows as $row) {
                $monthly[(int)$row['m']] = (int)$row['total'];
            }
            $result[(string)$y] = array_values($monthly);
        }
        
        jsonResponse([
            'years' => $result,
            'currentYear' => $currentYear,
            'startYear' => $startYear
        ]);
    }
    
    /**
     * 매출번호 생성 AJAX API
     */
    public function generateNumber() {
        $date = getParam('date', date('Y-m-d'));
        $saleNumber = generateSaleNumber($date);
        jsonResponse(['sale_number' => $saleNumber]);
    }
}
