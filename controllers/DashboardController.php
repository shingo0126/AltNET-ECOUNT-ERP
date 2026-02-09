<?php
class DashboardController {
    
    public function index() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '');
        $viewType = getParam('view', 'monthly'); // monthly or quarterly
        
        // Build date filter for period
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
        
        // Top companies/vendors WHERE clause builder
        $topCompanyWhere = "s.is_deleted=0 AND YEAR(s.sale_date)=?";
        $topCompanyParams = [$year];
        if ($viewType === 'quarterly' && $quarter) {
            $topCompanyWhere .= " AND QUARTER(s.sale_date)=?";
            $topCompanyParams[] = $quarter;
        } else {
            $topCompanyWhere .= " AND MONTH(s.sale_date)=?";
            $topCompanyParams[] = $month;
        }
        
        // Top 20 sales companies (for chart)
        $topCompanies = $db->fetchAll(
            "SELECT c.name, SUM(s.total_amount) as total 
             FROM sales s JOIN companies c ON s.company_id=c.id 
             WHERE $topCompanyWhere
             GROUP BY c.id, c.name ORDER BY total DESC LIMIT 20",
            $topCompanyParams
        );
        
        // ALL sales companies ranked (for detail view)
        $allCompanies = $db->fetchAll(
            "SELECT c.name, SUM(s.total_amount) as total 
             FROM sales s JOIN companies c ON s.company_id=c.id 
             WHERE $topCompanyWhere
             GROUP BY c.id, c.name ORDER BY total DESC",
            $topCompanyParams
        );
        
        $topVendorWhere = "p.is_deleted=0 AND YEAR(p.purchase_date)=?";
        $topVendorParams = [$year];
        if ($viewType === 'quarterly' && $quarter) {
            $topVendorWhere .= " AND QUARTER(p.purchase_date)=?";
            $topVendorParams[] = $quarter;
        } else {
            $topVendorWhere .= " AND MONTH(p.purchase_date)=?";
            $topVendorParams[] = $month;
        }
        
        // Top 20 purchase vendors (for chart)
        $topVendors = $db->fetchAll(
            "SELECT v.name, SUM(p.total_amount) as total 
             FROM purchases p JOIN vendors v ON p.vendor_id=v.id 
             WHERE $topVendorWhere
             GROUP BY v.id, v.name ORDER BY total DESC LIMIT 20",
            $topVendorParams
        );
        
        // ALL purchase vendors ranked (for detail view)
        $allVendors = $db->fetchAll(
            "SELECT v.name, SUM(p.total_amount) as total 
             FROM purchases p JOIN vendors v ON p.vendor_id=v.id 
             WHERE $topVendorWhere
             GROUP BY v.id, v.name ORDER BY total DESC",
            $topVendorParams
        );
        
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
    
    public function exportCompanies() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '');
        $viewType = getParam('view', 'monthly');
        
        $where = "s.is_deleted=0 AND YEAR(s.sale_date)=?";
        $params = [$year];
        if ($viewType === 'quarterly' && $quarter) {
            $where .= " AND QUARTER(s.sale_date)=?";
            $params[] = $quarter;
            $label = "{$year}_{$quarter}Q";
        } else {
            $where .= " AND MONTH(s.sale_date)=?";
            $params[] = $month;
            $label = "{$year}_{$month}";
        }
        
        $data = $db->fetchAll(
            "SELECT c.name, SUM(s.total_amount) as total 
             FROM sales s JOIN companies c ON s.company_id=c.id 
             WHERE $where GROUP BY c.id, c.name ORDER BY total DESC",
            $params
        );
        
        AuditLog::log('EXPORT', 'companies', null, null, null, "매출업체 순위 CSV 다운로드 ({$label})");
        
        $rows = array_map(function($d, $i) { return [$i + 1, $d['name'], number_format($d['total'])]; }, $data, array_keys($data));
        csvExport("매출업체순위_{$label}.csv", ['순위', '업체명', '매출총액'], $rows);
    }
    
    public function exportVendors() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '');
        $viewType = getParam('view', 'monthly');
        
        $where = "p.is_deleted=0 AND YEAR(p.purchase_date)=?";
        $params = [$year];
        if ($viewType === 'quarterly' && $quarter) {
            $where .= " AND QUARTER(p.purchase_date)=?";
            $params[] = $quarter;
            $label = "{$year}_{$quarter}Q";
        } else {
            $where .= " AND MONTH(p.purchase_date)=?";
            $params[] = $month;
            $label = "{$year}_{$month}";
        }
        
        $data = $db->fetchAll(
            "SELECT v.name, SUM(p.total_amount) as total 
             FROM purchases p JOIN vendors v ON p.vendor_id=v.id 
             WHERE $where GROUP BY v.id, v.name ORDER BY total DESC",
            $params
        );
        
        AuditLog::log('EXPORT', 'vendors', null, null, null, "매입업체 순위 CSV 다운로드 ({$label})");
        
        $rows = array_map(function($d, $i) { return [$i + 1, $d['name'], number_format($d['total'])]; }, $data, array_keys($data));
        csvExport("매입업체순위_{$label}.csv", ['순위', '업체명', '매입총액'], $rows);
    }
}
