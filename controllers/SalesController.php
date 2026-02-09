<?php
class SalesController {
    
    public function index() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $search = getParam('search', '');
        $page = max(1, (int)getParam('p', 1));
        $perPage = 30;
        
        $where = "s.is_deleted=0 AND YEAR(s.sale_date)=? AND MONTH(s.sale_date)=?";
        $params = [$year, $month];
        
        if ($search) {
            $where .= " AND (c.name LIKE ? OR s.sale_number LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        $total = $db->fetch("SELECT COUNT(*) as cnt FROM sales s LEFT JOIN companies c ON s.company_id=c.id WHERE $where", $params)['cnt'];
        $pag = paginate($total, $perPage, $page);
        
        $sales = $db->fetchAll(
            "SELECT s.*, c.name as company_name, si.name as item_name, si.sort_order as item_sort,
                    u.name as user_name,
                    COALESCE((SELECT SUM(p2.total_amount) FROM purchases p2 WHERE p2.sale_id=s.id AND p2.is_deleted=0),0) as purchase_total
             FROM sales s 
             LEFT JOIN companies c ON s.company_id=c.id
             LEFT JOIN sale_items si ON s.sale_item_id=si.id
             LEFT JOIN users u ON s.user_id=u.id
             WHERE $where 
             ORDER BY s.sale_date DESC, s.id DESC 
             LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
            $params
        );
        
        $years = $db->fetchAll("SELECT DISTINCT YEAR(sale_date) as y FROM sales WHERE is_deleted=0 UNION SELECT YEAR(CURDATE()) ORDER BY y DESC");
        
        $pageTitle = '매출/매입 관리';
        ob_start();
        include __DIR__ . '/../views/sales/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    public function create() {
        $db = Database::getInstance();
        $companies = $db->fetchAll("SELECT id, name FROM companies WHERE is_deleted=0 ORDER BY name");
        $vendors = $db->fetchAll("SELECT id, name FROM vendors WHERE is_deleted=0 ORDER BY name");
        $saleItems = $db->fetchAll("SELECT id, sort_order, name FROM sale_items WHERE is_deleted=0 ORDER BY sort_order");
        $saleNumber = generateSaleNumber();
        $purchaseNumber = generatePurchaseNumber();
        
        $sale = null;
        $saleDetails = [];
        $purchases = [];
        $isEdit = false;
        
        $pageTitle = '매출/매입 등록';
        ob_start();
        include __DIR__ . '/../views/sales/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    public function edit() {
        $db = Database::getInstance();
        $id = (int)getParam('id');
        
        $sale = $db->fetch("SELECT * FROM sales WHERE id=? AND is_deleted=0", [$id]);
        if (!$sale) { redirect('?page=sales'); }
        
        if (!Auth::canEdit($sale['user_id'])) {
            redirect('?page=sales');
        }
        
        $saleDetails = $db->fetchAll("SELECT * FROM sale_details WHERE sale_id=? ORDER BY sort_order", [$id]);
        $purchases = $db->fetchAll(
            "SELECT p.*, v.name as vendor_name FROM purchases p LEFT JOIN vendors v ON p.vendor_id=v.id WHERE p.sale_id=? AND p.is_deleted=0 ORDER BY p.id",
            [$id]
        );
        foreach ($purchases as &$p) {
            $p['details'] = $db->fetchAll("SELECT * FROM purchase_details WHERE purchase_id=? ORDER BY sort_order", [$p['id']]);
        }
        
        $companies = $db->fetchAll("SELECT id, name FROM companies WHERE is_deleted=0 ORDER BY name");
        $vendors = $db->fetchAll("SELECT id, name FROM vendors WHERE is_deleted=0 ORDER BY name");
        $saleItems = $db->fetchAll("SELECT id, sort_order, name FROM sale_items WHERE is_deleted=0 ORDER BY sort_order");
        $saleNumber = $sale['sale_number'];
        $purchaseNumber = generatePurchaseNumber();
        $isEdit = true;
        
        $pageTitle = '매출/매입 수정';
        ob_start();
        include __DIR__ . '/../views/sales/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('?page=sales');
        if (!CSRF::verify()) { redirect('?page=sales'); }
        
        $db = Database::getInstance();
        $isEdit = !empty(postParam('sale_id'));
        
        try {
            $db->beginTransaction();
            
            $saleData = [
                'sale_date'    => postParam('sale_date', date('Y-m-d')),
                'company_id'   => (int)postParam('company_id'),
                'sale_item_id' => postParam('sale_item_id') ?: null,
                'total_amount' => (int)str_replace(',', '', postParam('sale_total', '0')),
                'vat_amount'   => (int)str_replace(',', '', postParam('sale_vat', '0')),
                'user_id'      => Session::getUserId(),
            ];
            
            if ($isEdit) {
                $saleId = (int)postParam('sale_id');
                $old = $db->fetch("SELECT * FROM sales WHERE id=?", [$saleId]);
                $db->update('sales', $saleData, 'id=?', [$saleId]);
                
                // Delete old details and re-insert
                $db->delete('sale_details', 'sale_id=?', [$saleId]);
                AuditLog::log('UPDATE', 'sales', $saleId, $old, $saleData);
            } else {
                $saleData['sale_number'] = postParam('sale_number', generateSaleNumber($saleData['sale_date']));
                $saleId = $db->insert('sales', $saleData);
                AuditLog::log('INSERT', 'sales', $saleId, null, $saleData);
            }
            
            // Save sale details
            $productNames = $_POST['product_name'] ?? [];
            $unitPrices = $_POST['unit_price'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            
            for ($i = 0; $i < count($productNames); $i++) {
                if (empty(trim($productNames[$i]))) continue;
                $price = (int)str_replace(',', '', $unitPrices[$i] ?? '0');
                $qty = (int)($quantities[$i] ?? 1);
                $db->insert('sale_details', [
                    'sale_id'      => $saleId,
                    'product_name' => trim($productNames[$i]),
                    'unit_price'   => $price,
                    'quantity'     => $qty,
                    'subtotal'     => $price * $qty,
                    'sort_order'   => $i,
                ]);
            }
            
            // Save purchases
            $pDates = $_POST['p_date'] ?? [];
            $pVendors = $_POST['p_vendor_id'] ?? [];
            $pTotals = $_POST['p_total'] ?? [];
            $pVats = $_POST['p_vat'] ?? [];
            $pProducts = $_POST['p_product_name'] ?? [];
            $pPrices = $_POST['p_unit_price'] ?? [];
            $pQtys = $_POST['p_quantity'] ?? [];
            $pIds = $_POST['p_id'] ?? [];
            
            // Remove old purchases if editing
            if ($isEdit) {
                $oldPurchases = $db->fetchAll("SELECT id FROM purchases WHERE sale_id=? AND is_deleted=0", [$saleId]);
                foreach ($oldPurchases as $op) {
                    $db->delete('purchase_details', 'purchase_id=?', [$op['id']]);
                }
                $db->delete('purchases', 'sale_id=? AND is_deleted=0', [$saleId]);
            }
            
            if (!empty($pDates)) {
                for ($pi = 0; $pi < count($pDates); $pi++) {
                    if (empty($pVendors[$pi])) continue;
                    
                    $pData = [
                        'purchase_number' => generatePurchaseNumber($pDates[$pi]),
                        'sale_id'         => $saleId,
                        'purchase_date'   => $pDates[$pi],
                        'vendor_id'       => (int)$pVendors[$pi],
                        'total_amount'    => (int)str_replace(',', '', $pTotals[$pi] ?? '0'),
                        'vat_amount'      => (int)str_replace(',', '', $pVats[$pi] ?? '0'),
                        'user_id'         => Session::getUserId(),
                    ];
                    $purchaseId = $db->insert('purchases', $pData);
                    
                    // Purchase details
                    $pProdArr = $pProducts[$pi] ?? [];
                    $pPriceArr = $pPrices[$pi] ?? [];
                    $pQtyArr = $pQtys[$pi] ?? [];
                    
                    if (is_array($pProdArr)) {
                        for ($di = 0; $di < count($pProdArr); $di++) {
                            if (empty(trim($pProdArr[$di]))) continue;
                            $dprice = (int)str_replace(',', '', $pPriceArr[$di] ?? '0');
                            $dqty = (int)($pQtyArr[$di] ?? 1);
                            $db->insert('purchase_details', [
                                'purchase_id' => $purchaseId,
                                'product_name' => trim($pProdArr[$di]),
                                'unit_price'  => $dprice,
                                'quantity'    => $dqty,
                                'subtotal'    => $dprice * $dqty,
                                'sort_order'  => $di,
                            ]);
                        }
                    }
                    
                    AuditLog::log('INSERT', 'purchases', $purchaseId, null, $pData);
                }
            }
            
            $db->commit();
            Session::set('flash_message', $isEdit ? '매출/매입이 수정되었습니다.' : '매출/매입이 등록되었습니다.');
            Session::set('flash_type', 'success');
            redirect('?page=sales');
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Sales save error: " . $e->getMessage());
            Session::set('flash_message', '저장 중 오류가 발생했습니다: ' . $e->getMessage());
            Session::set('flash_type', 'danger');
            redirect('?page=sales');
        }
    }
    
    public function delete() {
        if (!CSRF::verify($_GET['token'] ?? '')) redirect('?page=sales');
        
        $db = Database::getInstance();
        $id = (int)getParam('id');
        $sale = $db->fetch("SELECT * FROM sales WHERE id=?", [$id]);
        
        if ($sale && Auth::canEdit($sale['user_id'])) {
            $db->update('sales', ['is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s')], 'id=?', [$id]);
            // Also soft-delete related purchases
            $db->update('purchases', ['is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s')], 'sale_id=? AND is_deleted=0', [$id]);
            AuditLog::log('DELETE', 'sales', $id, $sale, null, '매출 삭제 (Soft Delete)');
        }
        
        redirect('?page=sales');
    }
    
    public function export() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        
        $sales = $db->fetchAll(
            "SELECT s.sale_number, s.sale_date, c.name as company_name, s.total_amount, s.vat_amount, 
                    COALESCE((SELECT SUM(p.total_amount) FROM purchases p WHERE p.sale_id=s.id AND p.is_deleted=0),0) as purchase_total,
                    si.sort_order as item_sort, si.name as item_name
             FROM sales s 
             LEFT JOIN companies c ON s.company_id=c.id 
             LEFT JOIN sale_items si ON s.sale_item_id=si.id
             WHERE s.is_deleted=0 AND YEAR(s.sale_date)=? AND MONTH(s.sale_date)=?
             ORDER BY s.sale_date",
            [$year, $month]
        );
        
        AuditLog::log('EXPORT', 'sales', null, null, null, "매출 CSV 다운로드 ({$year}-{$month})");
        
        $headers = ['매출번호', '매출일자', '업체명', '제품코드', '매출총액', '부가세', '매입총액', '영업이익'];
        $rows = [];
        foreach ($sales as $s) {
            $profit = $s['total_amount'] - $s['purchase_total'];
            $rows[] = [
                $s['sale_number'], $s['sale_date'], $s['company_name'],
                $s['item_sort'] . '.' . $s['item_name'],
                number_format($s['total_amount']), number_format($s['vat_amount']),
                number_format($s['purchase_total']), number_format($profit)
            ];
        }
        
        csvExport("매출내역_{$year}_{$month}.csv", $headers, $rows);
    }
}
