<?php
class SalesController {
    
    public function index() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '1');
        $viewType = getParam('view', 'monthly'); // monthly, quarterly, yearly
        $search = getParam('search', '');
        $page = max(1, (int)getParam('p', 1));
        $perPage = 20;
        
        // === 조회기간 라벨 ===
        if ($viewType === 'yearly') {
            $periodLabel = "{$year}년 전체";
        } elseif ($viewType === 'quarterly') {
            $qNames = ['', '1분기(1~3월)', '2분기(4~6월)', '3분기(7~9월)', '4분기(10~12월)'];
            $periodLabel = "{$year}년 " . ($qNames[(int)$quarter] ?? "{$quarter}분기");
        } else {
            $periodLabel = "{$year}년 {$month}월";
        }
        
        // === 매출 내역 필터 ===
        $where = "s.is_deleted=0 AND YEAR(s.sale_date)=?";
        $params = [$year];
        if ($viewType === 'monthly') {
            $where .= " AND MONTH(s.sale_date)=?";
            $params[] = $month;
        } elseif ($viewType === 'quarterly') {
            $where .= " AND QUARTER(s.sale_date)=?";
            $params[] = $quarter;
        }
        // yearly: 연도 전체 조회 (추가 조건 없음)
        
        if ($search) {
            $where .= " AND (c.name LIKE ? OR s.sale_number LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        $total = $db->fetch("SELECT COUNT(*) as cnt FROM sales s LEFT JOIN companies c ON s.company_id=c.id WHERE $where", $params)['cnt'];
        $pag = paginate($total, $perPage, $page);
        
        $sales = $db->fetchAll(
            "SELECT s.*, s.delivery_date, c.name as company_name,
                    u.name as user_name,
                    COALESCE((SELECT SUM(p2.total_amount) FROM purchases p2 WHERE p2.sale_id=s.id AND p2.is_deleted=0),0) as purchase_total,
                    fi.name as first_item_name, fi.sort_order as first_item_sort,
                    (SELECT COUNT(*) FROM sale_details sd2 WHERE sd2.sale_id=s.id) as detail_count,
                    (SELECT sd3.product_name FROM sale_details sd3 WHERE sd3.sale_id=s.id ORDER BY sd3.sort_order ASC LIMIT 1) as first_product_name,
                    (SELECT MIN(p3.purchase_date) FROM purchases p3 WHERE p3.sale_id=s.id AND p3.is_deleted=0) as first_purchase_date,
                    (SELECT v2.name FROM purchases p4 LEFT JOIN vendors v2 ON p4.vendor_id=v2.id WHERE p4.sale_id=s.id AND p4.is_deleted=0 ORDER BY p4.id ASC LIMIT 1) as first_vendor_name,
                    (SELECT COUNT(DISTINCT p5.id) FROM purchases p5 WHERE p5.sale_id=s.id AND p5.is_deleted=0) as purchase_count
             FROM sales s 
             LEFT JOIN companies c ON s.company_id=c.id
             LEFT JOIN users u ON s.user_id=u.id
             LEFT JOIN sale_details sd_first ON sd_first.sale_id=s.id AND sd_first.sort_order=0
             LEFT JOIN sale_items fi ON fi.id=sd_first.sale_item_id
             WHERE $where 
             ORDER BY s.sale_date DESC, s.id DESC 
             LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
            $params
        );
        
        $years = $db->fetchAll("SELECT DISTINCT YEAR(sale_date) as y FROM sales WHERE is_deleted=0 UNION SELECT YEAR(CURDATE()) ORDER BY y DESC");
        
        // === 매출 집계 (업체별 합산) ===
        $saleSumPage = max(1, (int)getParam('sp', 1));
        $saleSumWhere = "s.is_deleted=0 AND YEAR(s.sale_date)=?";
        $saleSumParams = [$year];
        if ($viewType === 'monthly') {
            $saleSumWhere .= " AND MONTH(s.sale_date)=?";
            $saleSumParams[] = $month;
        } elseif ($viewType === 'quarterly') {
            $saleSumWhere .= " AND QUARTER(s.sale_date)=?";
            $saleSumParams[] = $quarter;
        }
        if ($search) {
            $saleSumWhere .= " AND (c.name LIKE ? OR s.sale_number LIKE ?)";
            $saleSumParams[] = "%{$search}%";
            $saleSumParams[] = "%{$search}%";
        }
        $saleSumTotal = $db->fetch(
            "SELECT COUNT(*) as cnt FROM (SELECT c.id FROM sales s LEFT JOIN companies c ON s.company_id=c.id WHERE $saleSumWhere GROUP BY c.id) sub",
            $saleSumParams
        )['cnt'];
        $saleSumPag = paginate($saleSumTotal, $perPage, $saleSumPage);
        $saleSummary = $db->fetchAll(
            "SELECT c.name as company_name, COUNT(s.id) as sale_count,
                    SUM(s.total_amount) as total_sales, SUM(s.vat_amount) as total_vat,
                    COALESCE(SUM((SELECT SUM(p2.total_amount) FROM purchases p2 WHERE p2.sale_id=s.id AND p2.is_deleted=0)),0) as total_purchases
             FROM sales s
             LEFT JOIN companies c ON s.company_id=c.id
             WHERE $saleSumWhere
             GROUP BY c.id, c.name
             ORDER BY total_sales DESC
             LIMIT {$saleSumPag['per_page']} OFFSET {$saleSumPag['offset']}",
            $saleSumParams
        );
        
        // === 매입 집계 (업체별 합산) ===
        $purchSumPage = max(1, (int)getParam('pp', 1));
        $purchSumWhere = "p.is_deleted=0 AND YEAR(p.purchase_date)=?";
        $purchSumParams = [$year];
        if ($viewType === 'monthly') {
            $purchSumWhere .= " AND MONTH(p.purchase_date)=?";
            $purchSumParams[] = $month;
        } elseif ($viewType === 'quarterly') {
            $purchSumWhere .= " AND QUARTER(p.purchase_date)=?";
            $purchSumParams[] = $quarter;
        }
        if ($search) {
            $purchSumWhere .= " AND (v.name LIKE ? OR p.purchase_number LIKE ?)";
            $purchSumParams[] = "%{$search}%";
            $purchSumParams[] = "%{$search}%";
        }
        $purchSumTotal = $db->fetch(
            "SELECT COUNT(*) as cnt FROM (
                SELECT COALESCE(pd.vendor_id, p.vendor_id) as vid
                FROM purchase_details pd
                JOIN purchases p ON pd.purchase_id = p.id
                LEFT JOIN vendors v ON COALESCE(pd.vendor_id, p.vendor_id) = v.id
                WHERE $purchSumWhere
                GROUP BY vid
            ) sub",
            $purchSumParams
        )['cnt'];
        $purchSumPag = paginate($purchSumTotal, $perPage, $purchSumPage);
        $purchSummary = $db->fetchAll(
            "SELECT v.name as vendor_name, COUNT(DISTINCT p.id) as purchase_count,
                    SUM(pd.subtotal) as total_purchases, ROUND(SUM(pd.subtotal) * 0.1) as total_vat
             FROM purchase_details pd
             JOIN purchases p ON pd.purchase_id = p.id
             LEFT JOIN vendors v ON COALESCE(pd.vendor_id, p.vendor_id) = v.id
             WHERE $purchSumWhere
             GROUP BY v.id, v.name
             ORDER BY total_purchases DESC
             LIMIT {$purchSumPag['per_page']} OFFSET {$purchSumPag['offset']}",
            $purchSumParams
        );
        
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
        // 오늘 날짜 기준 초기 매출번호 (일자 변경 시 AJAX로 재생성)
        $saleNumber = generateSaleNumber(date('Y-m-d'));
        $purchaseNumber = generatePurchaseNumber(date('Y-m-d'));
        
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
            
            $companyId = postParam('company_id');
            $deliveryDate = postParam('delivery_date', '');
            
            // 매출 제품이 0건인지 판별 (매입만 단독 등록 여부)
            $productNames = $_POST['product_name'] ?? [];
            $saleTotal = (int)str_replace(',', '', postParam('sale_total', '0'));
            $hasSaleProducts = false;
            foreach ($productNames as $pn) {
                if (!empty(trim($pn))) { $hasSaleProducts = true; break; }
            }
            
            // 매출 제품이 없으면 첫 번째 매입일자를 sale_date로 사용
            $saleDateValue = postParam('sale_date', date('Y-m-d'));
            if (!$hasSaleProducts || $saleTotal <= 0) {
                $pDatesCheck = $_POST['p_date'] ?? [];
                if (!empty($pDatesCheck) && !empty($pDatesCheck[0])) {
                    $saleDateValue = $pDatesCheck[0];
                }
            }
            
            $saleData = [
                'sale_date'     => $saleDateValue,
                'delivery_date' => !empty($deliveryDate) ? $deliveryDate : null,
                'company_id'    => !empty($companyId) ? (int)$companyId : null,
                'sale_item_id'  => null,  // 행별 sale_item_id로 이동됨 (호환성 유지)
                'total_amount'  => $saleTotal,
                'vat_amount'    => (int)str_replace(',', '', postParam('sale_vat', '0')),
                'user_id'       => Session::getUserId(),
            ];
            
            if ($isEdit) {
                $saleId = (int)postParam('sale_id');
                $old = $db->fetch("SELECT * FROM sales WHERE id=?", [$saleId]);
                
                // 수정 시에도 매출일자가 변경되었으면 매출번호 재생성
                if ($old && $old['sale_date'] !== $saleData['sale_date']) {
                    $saleData['sale_number'] = generateSaleNumber($saleData['sale_date']);
                }
                
                $db->update('sales', $saleData, 'id=?', [$saleId]);
                
                // Delete old details and re-insert
                $db->delete('sale_details', 'sale_id=?', [$saleId]);
                AuditLog::log('UPDATE', 'sales', $saleId, $old, $saleData);
            } else {
                // 폼에서 전달된 매출번호 사용 (매출일자 기반으로 이미 생성됨)
                $saleData['sale_number'] = postParam('sale_number', generateSaleNumber($saleData['sale_date']));
                $saleId = $db->insert('sales', $saleData);
                AuditLog::log('INSERT', 'sales', $saleId, null, $saleData);
            }
            
            // Save sale details (행별 sale_item_id 포함)
            $productNames = $_POST['product_name'] ?? [];
            $saleItemIds = $_POST['sale_item_id'] ?? [];
            $unitPrices = $_POST['unit_price'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            
            for ($i = 0; $i < count($productNames); $i++) {
                if (empty(trim($productNames[$i]))) continue;
                $price = (int)str_replace(',', '', $unitPrices[$i] ?? '0');
                $qty = (int)($quantities[$i] ?? 1);
                $itemId = !empty($saleItemIds[$i]) ? (int)$saleItemIds[$i] : null;
                $db->insert('sale_details', [
                    'sale_id'      => $saleId,
                    'product_name' => trim($productNames[$i]),
                    'sale_item_id' => $itemId,
                    'unit_price'   => $price,
                    'quantity'     => $qty,
                    'subtotal'     => $price * $qty,
                    'sort_order'   => $i,
                ]);
            }
            
            // Save purchases (매입업체는 행별로 이동됨)
            $pDates = $_POST['p_date'] ?? [];
            $pTotals = $_POST['p_total'] ?? [];
            $pVats = $_POST['p_vat'] ?? [];
            $pProducts = $_POST['p_product_name'] ?? [];
            $pPrices = $_POST['p_unit_price'] ?? [];
            $pQtys = $_POST['p_quantity'] ?? [];
            $pVendorsByBlock = $_POST['p_vendor_id'] ?? [];  // p_vendor_id[블록idx][행idx]
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
                    // 매입업체는 행별로 관리됨 - 블록의 첫 번째 행 vendor_id를 대표로 사용
                    $blockVendors = $pVendorsByBlock[$pi] ?? [];
                    $firstVendorId = 0;
                    if (is_array($blockVendors)) {
                        foreach ($blockVendors as $bv) {
                            if (!empty($bv)) { $firstVendorId = (int)$bv; break; }
                        }
                    }
                    
                    // 매입 제품이 하나도 없으면 스킵
                    $pProdArr = $pProducts[$pi] ?? [];
                    $hasProducts = false;
                    if (is_array($pProdArr)) {
                        foreach ($pProdArr as $pn) {
                            if (!empty(trim($pn))) { $hasProducts = true; break; }
                        }
                    }
                    if (!$hasProducts) continue;
                    
                    $pData = [
                        'purchase_number' => generatePurchaseNumber($pDates[$pi]),
                        'sale_id'         => $saleId,
                        'purchase_date'   => $pDates[$pi],
                        'vendor_id'       => $firstVendorId,
                        'total_amount'    => (int)str_replace(',', '', $pTotals[$pi] ?? '0'),
                        'vat_amount'      => (int)str_replace(',', '', $pVats[$pi] ?? '0'),
                        'user_id'         => Session::getUserId(),
                    ];
                    $purchaseId = $db->insert('purchases', $pData);
                    
                    // Purchase details (행별 vendor_id 포함)
                    $pPriceArr = $pPrices[$pi] ?? [];
                    $pQtyArr = $pQtys[$pi] ?? [];
                    $pVendorArr = $blockVendors;
                    
                    if (is_array($pProdArr)) {
                        for ($di = 0; $di < count($pProdArr); $di++) {
                            if (empty(trim($pProdArr[$di]))) continue;
                            $dprice = (int)str_replace(',', '', $pPriceArr[$di] ?? '0');
                            $dqty = (int)($pQtyArr[$di] ?? 1);
                            $dVendorId = !empty($pVendorArr[$di]) ? (int)$pVendorArr[$di] : null;
                            $db->insert('purchase_details', [
                                'purchase_id' => $purchaseId,
                                'product_name' => trim($pProdArr[$di]),
                                'vendor_id'   => $dVendorId,
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
    
    /**
     * AJAX API: 매출일자 기준 매출번호 자동생성
     */
    public function generateNumber() {
        $date = getParam('date', date('Y-m-d'));
        $saleNumber = generateSaleNumber($date);
        $purchaseNumber = generatePurchaseNumber($date);
        jsonResponse([
            'sale_number' => $saleNumber,
            'purchase_number' => $purchaseNumber,
        ]);
    }
    
    public function export() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '1');
        $viewType = getParam('view', 'monthly');
        
        $where = "s.is_deleted=0 AND YEAR(s.sale_date)=?";
        $params = [$year];
        $fileSuffix = "{$year}";
        if ($viewType === 'monthly') {
            $where .= " AND MONTH(s.sale_date)=?";
            $params[] = $month;
            $fileSuffix .= "_{$month}월";
        } elseif ($viewType === 'quarterly') {
            $where .= " AND QUARTER(s.sale_date)=?";
            $params[] = $quarter;
            $fileSuffix .= "_{$quarter}분기";
        } else {
            $fileSuffix .= "_연간";
        }
        
        // 매출 기본 정보 조회 (리스트 컬럼과 동일하게)
        $sales = $db->fetchAll(
            "SELECT s.id, s.sale_number, s.sale_date, s.delivery_date, c.name as company_name, 
                    s.total_amount, s.vat_amount, 
                    COALESCE((SELECT SUM(p.total_amount) FROM purchases p WHERE p.sale_id=s.id AND p.is_deleted=0),0) as purchase_total,
                    (SELECT MIN(p3.purchase_date) FROM purchases p3 WHERE p3.sale_id=s.id AND p3.is_deleted=0) as first_purchase_date,
                    (SELECT v2.name FROM purchases p4 LEFT JOIN vendors v2 ON p4.vendor_id=v2.id WHERE p4.sale_id=s.id AND p4.is_deleted=0 ORDER BY p4.id ASC LIMIT 1) as first_vendor_name,
                    (SELECT COUNT(DISTINCT p5.id) FROM purchases p5 WHERE p5.sale_id=s.id AND p5.is_deleted=0) as purchase_count
             FROM sales s 
             LEFT JOIN companies c ON s.company_id=c.id 
             WHERE $where
             ORDER BY s.sale_date",
            $params
        );
        
        AuditLog::log('EXPORT', 'sales', null, null, null, "매출 CSV 다운로드 ({$fileSuffix})");
        
        // 행 확장 방식: 제품코드별 별도 행 출력 (리스트 컬럼과 동일)
        $headers = ['매출번호', '매출일자', '출고일자', '매입일자', '업체명', '제품코드', '제품명', '매입업체', '단가', '수량', '소계', '매출총액', '부가세', '매입총액', '영업이익'];
        $rows = [];
        foreach ($sales as $s) {
            $profit = $s['total_amount'] - $s['purchase_total'];
            $deliveryDate = $s['delivery_date'] ?? '';
            $purchaseDate = $s['first_purchase_date'] ?? '';
            $vendorName = $s['first_vendor_name'] ?? '';
            $vendorExtra = ((int)($s['purchase_count'] ?? 0) > 1) ? ' 외 ' . ((int)$s['purchase_count'] - 1) . '건' : '';
            
            // 해당 매출의 전체 sale_details 조회
            $details = $db->fetchAll(
                "SELECT sd.product_name, sd.unit_price, sd.quantity, sd.subtotal,
                        si.sort_order as item_sort, si.name as item_name
                 FROM sale_details sd
                 LEFT JOIN sale_items si ON sd.sale_item_id = si.id
                 WHERE sd.sale_id = ?
                 ORDER BY sd.sort_order",
                [$s['id']]
            );
            
            if (empty($details)) {
                // 제품 상세 없는 경우 기본 행 1개
                $rows[] = [
                    $s['sale_number'], $s['sale_date'], $deliveryDate, $purchaseDate,
                    $s['company_name'] ?? '미지정',
                    '-', '-', $vendorName . $vendorExtra,
                    '', '', '',
                    number_format($s['total_amount']), number_format($s['vat_amount']),
                    number_format($s['purchase_total']), number_format($profit)
                ];
            } else {
                foreach ($details as $idx => $d) {
                    $itemCode = $d['item_sort'] ? $d['item_sort'] . '.' . $d['item_name'] : '-';
                    
                    if ($idx === 0) {
                        // 첫 번째 제품행: 매출 기본 정보 포함
                        $rows[] = [
                            $s['sale_number'], $s['sale_date'], $deliveryDate, $purchaseDate,
                            $s['company_name'] ?? '미지정',
                            $itemCode, $d['product_name'], $vendorName . $vendorExtra,
                            number_format($d['unit_price']), $d['quantity'], number_format($d['subtotal']),
                            number_format($s['total_amount']), number_format($s['vat_amount']),
                            number_format($s['purchase_total']), number_format($profit)
                        ];
                    } else {
                        // 2번째 이후 제품행: 매출번호~업체명은 빈칸, 제품 상세만 표시
                        $rows[] = [
                            '', '', '', '', '',
                            $itemCode, $d['product_name'], '',
                            number_format($d['unit_price']), $d['quantity'], number_format($d['subtotal']),
                            '', '', '', ''
                        ];
                    }
                }
            }
        }
        
        csvExport("매출내역_{$fileSuffix}.csv", $headers, $rows);
    }
    
    /**
     * 매출 집계 CSV 다운로드 (업체별)
     */
    public function exportSaleSummary() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '1');
        $viewType = getParam('view', 'monthly');
        
        $where = "s.is_deleted=0 AND YEAR(s.sale_date)=?";
        $params = [$year];
        $fileSuffix = "{$year}";
        if ($viewType === 'monthly') {
            $where .= " AND MONTH(s.sale_date)=?";
            $params[] = $month;
            $fileSuffix .= "_{$month}월";
        } elseif ($viewType === 'quarterly') {
            $where .= " AND QUARTER(s.sale_date)=?";
            $params[] = $quarter;
            $fileSuffix .= "_{$quarter}분기";
        } else {
            $fileSuffix .= "_연간";
        }
        
        $data = $db->fetchAll(
            "SELECT c.name as company_name, COUNT(s.id) as sale_count,
                    SUM(s.total_amount) as total_sales, SUM(s.vat_amount) as total_vat,
                    COALESCE(SUM((SELECT SUM(p2.total_amount) FROM purchases p2 WHERE p2.sale_id=s.id AND p2.is_deleted=0)),0) as total_purchases
             FROM sales s
             LEFT JOIN companies c ON s.company_id=c.id
             WHERE $where
             GROUP BY c.id, c.name
             ORDER BY total_sales DESC",
            $params
        );
        
        AuditLog::log('EXPORT', 'sales', null, null, null, "매출집계 CSV 다운로드 ({$fileSuffix})");
        
        $headers = ['순위', '업체명', '건수', '매출합계', '부가세합계', '매입합계', '영업이익'];
        $rows = [];
        foreach ($data as $i => $r) {
            $profit = $r['total_sales'] - $r['total_purchases'];
            $rows[] = [
                $i + 1, $r['company_name'], $r['sale_count'],
                number_format($r['total_sales']), number_format($r['total_vat']),
                number_format($r['total_purchases']), number_format($profit)
            ];
        }
        
        csvExport("매출집계_{$fileSuffix}.csv", $headers, $rows);
    }
    
    /**
     * 매입 집계 CSV 다운로드 (업체별)
     */
    public function exportPurchSummary() {
        $db = Database::getInstance();
        $year = getParam('year', date('Y'));
        $month = getParam('month', date('m'));
        $quarter = getParam('quarter', '1');
        $viewType = getParam('view', 'monthly');
        
        $where = "p.is_deleted=0 AND YEAR(p.purchase_date)=?";
        $params = [$year];
        $fileSuffix = "{$year}";
        if ($viewType === 'monthly') {
            $where .= " AND MONTH(p.purchase_date)=?";
            $params[] = $month;
            $fileSuffix .= "_{$month}월";
        } elseif ($viewType === 'quarterly') {
            $where .= " AND QUARTER(p.purchase_date)=?";
            $params[] = $quarter;
            $fileSuffix .= "_{$quarter}분기";
        } else {
            $fileSuffix .= "_연간";
        }
        
        $data = $db->fetchAll(
            "SELECT v.name as vendor_name, COUNT(DISTINCT p.id) as purchase_count,
                    SUM(pd.subtotal) as total_purchases, ROUND(SUM(pd.subtotal) * 0.1) as total_vat
             FROM purchase_details pd
             JOIN purchases p ON pd.purchase_id = p.id
             LEFT JOIN vendors v ON COALESCE(pd.vendor_id, p.vendor_id) = v.id
             WHERE $where
             GROUP BY v.id, v.name
             ORDER BY total_purchases DESC",
            $params
        );
        
        AuditLog::log('EXPORT', 'purchases', null, null, null, "매입집계 CSV 다운로드 ({$fileSuffix})");
        
        $headers = ['순위', '업체명', '건수', '매입합계', '부가세합계'];
        $rows = [];
        foreach ($data as $i => $r) {
            $rows[] = [
                $i + 1, $r['vendor_name'], $r['purchase_count'],
                number_format($r['total_purchases']), number_format($r['total_vat'])
            ];
        }
        
        csvExport("매입집계_{$fileSuffix}.csv", $headers, $rows);
    }
}
