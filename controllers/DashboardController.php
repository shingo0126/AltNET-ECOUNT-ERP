<?php
class DashboardController {
    
    public function index() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '');
        $viewType = getParam('view', 'monthly'); // monthly or quarterly
        
        // Monthly sales total
        $monthlySales = $db->fetch(
            "SELECT COALESCE(SUM(total_amount),0) as total, COUNT(*) as cnt 
             FROM sales WHERE YEAR(sale_date)=? AND MONTH(sale_date)=? AND is_deleted=0",
            [$year, $month]
        );
        
        // Monthly purchases total
        $monthlyPurchases = $db->fetch(
            "SELECT COALESCE(SUM(total_amount),0) as total 
             FROM purchases WHERE YEAR(purchase_date)=? AND MONTH(purchase_date)=? AND is_deleted=0",
            [$year, $month]
        );
        
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
        
        // Top 20 sales companies
        $topCompanyWhere = "s.is_deleted=0 AND YEAR(s.sale_date)=?";
        $topCompanyParams = [$year];
        if ($viewType === 'quarterly' && $quarter) {
            $topCompanyWhere .= " AND QUARTER(s.sale_date)=?";
            $topCompanyParams[] = $quarter;
        } else {
            $topCompanyWhere .= " AND MONTH(s.sale_date)=?";
            $topCompanyParams[] = $month;
        }
        
        $topCompanies = $db->fetchAll(
            "SELECT c.name, SUM(s.total_amount) as total 
             FROM sales s JOIN companies c ON s.company_id=c.id 
             WHERE $topCompanyWhere
             GROUP BY c.id, c.name ORDER BY total DESC LIMIT 20",
            $topCompanyParams
        );
        
        // Top 20 purchase vendors
        $topVendorWhere = "p.is_deleted=0 AND YEAR(p.purchase_date)=?";
        $topVendorParams = [$year];
        if ($viewType === 'quarterly' && $quarter) {
            $topVendorWhere .= " AND QUARTER(p.purchase_date)=?";
            $topVendorParams[] = $quarter;
        } else {
            $topVendorWhere .= " AND MONTH(p.purchase_date)=?";
            $topVendorParams[] = $month;
        }
        
        $topVendors = $db->fetchAll(
            "SELECT v.name, SUM(p.total_amount) as total 
             FROM purchases p JOIN vendors v ON p.vendor_id=v.id 
             WHERE $topVendorWhere
             GROUP BY v.id, v.name ORDER BY total DESC LIMIT 20",
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
}
