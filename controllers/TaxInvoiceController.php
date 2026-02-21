<?php
/**
 * AltNET Ecount ERP - 세금계산서 발행 요청 관리 Controller (Refactored)
 * - 매출/매입 등록 페이지와 동일한 구조
 * - 완료 시 매출/매입 관리 리스트에 자동 이관
 */
class TaxInvoiceController {
    
    public function index() {
        $db = Database::getInstance();
        
        $reqPage = max(1, (int)getParam('rp', 1));
        $compPage = max(1, (int)getParam('cp', 1));
        $perPage = 25;
        $isAdmin = Auth::hasRole(['admin']);
        
        // === 집계 카운트 ===
        $reqOnlyCount = (int)$db->fetch(
            "SELECT COUNT(*) as cnt FROM tax_invoices WHERE is_deleted=0 AND status='requested'"
        )['cnt'];
        $pendingCount = (int)$db->fetch(
            "SELECT COUNT(*) as cnt FROM tax_invoices WHERE is_deleted=0 AND status='pending'"
        )['cnt'];
        $reqCount = $reqOnlyCount + $pendingCount;
        $compCount = (int)$db->fetch(
            "SELECT COUNT(*) as cnt FROM tax_invoices WHERE is_deleted=0 AND status='completed'"
        )['cnt'];
        
        // === 발행 요청 리스트 (요청+보류) ===
        $reqPag = paginate($reqCount, $perPage, $reqPage);
        $requestList = $db->fetchAll(
            "SELECT t.*, c.name as company_name
             FROM tax_invoices t
             LEFT JOIN companies c ON t.company_id=c.id
             WHERE t.is_deleted=0 AND t.status IN ('requested','pending')
             ORDER BY t.request_date DESC, t.id DESC
             LIMIT {$reqPag['per_page']} OFFSET {$reqPag['offset']}"
        );
        
        // === 발행 완료 리스트 ===
        $compPag = paginate($compCount, $perPage, $compPage);
        $completedList = $db->fetchAll(
            "SELECT t.*, c.name as company_name
             FROM tax_invoices t
             LEFT JOIN companies c ON t.company_id=c.id
             WHERE t.is_deleted=0 AND t.status='completed'
             ORDER BY t.request_date DESC, t.id DESC
             LIMIT {$compPag['per_page']} OFFSET {$compPag['offset']}"
        );
        
        // 업체/매입업체/판매항목 목록
        $companies = $db->fetchAll("SELECT id, name FROM companies WHERE is_deleted=0 ORDER BY name");
        $vendors = $db->fetchAll("SELECT id, name FROM vendors WHERE is_deleted=0 ORDER BY name");
        $saleItems = $db->fetchAll("SELECT id, sort_order, name FROM sale_items WHERE is_deleted=0 ORDER BY sort_order");
        
        $pageTitle = '세금계산서 발행 요청';
        ob_start();
        include __DIR__ . '/../views/taxinvoice/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    /**
     * 등록 폼 (전체 페이지)
     */
    public function create() {
        $db = Database::getInstance();
        $companies = $db->fetchAll("SELECT id, name FROM companies WHERE is_deleted=0 ORDER BY name");
        $vendors = $db->fetchAll("SELECT id, name FROM vendors WHERE is_deleted=0 ORDER BY name");
        $saleItems = $db->fetchAll("SELECT id, sort_order, name FROM sale_items WHERE is_deleted=0 ORDER BY sort_order");
        
        $saleNumber = generateSaleNumber(date('Y-m-d'));
        
        $invoice = null;
        $invoiceDetails = [];
        $invoicePurchases = [];
        $isEdit = false;
        
        $pageTitle = '세금계산서 발행 요청 등록';
        ob_start();
        include __DIR__ . '/../views/taxinvoice/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    /**
     * 수정 폼 (전체 페이지)
     */
    public function edit() {
        $db = Database::getInstance();
        $id = (int)getParam('id');
        
        $invoice = $db->fetch(
            "SELECT * FROM tax_invoices WHERE id=? AND is_deleted=0", [$id]
        );
        if (!$invoice) { redirect('?page=taxinvoice'); }
        
        // 완료 건은 수정 불가
        if ($invoice['status'] === 'completed') {
            Session::set('flash_message', '완료된 세금계산서는 수정할 수 없습니다.');
            Session::set('flash_type', 'danger');
            redirect('?page=taxinvoice');
        }
        
        if (!Auth::hasRole(['admin'])) { redirect('?page=taxinvoice'); }
        
        $invoiceDetails = $db->fetchAll(
            "SELECT * FROM tax_invoice_details WHERE tax_invoice_id=? ORDER BY sort_order", [$id]
        );
        
        // 매입 정보 로드
        $invoicePurchases = $db->fetchAll(
            "SELECT * FROM tax_invoice_purchases WHERE tax_invoice_id=? ORDER BY id", [$id]
        );
        foreach ($invoicePurchases as &$p) {
            $p['details'] = $db->fetchAll(
                "SELECT * FROM tax_invoice_purchase_details WHERE tax_invoice_purchase_id=? ORDER BY sort_order",
                [$p['id']]
            );
        }
        
        $companies = $db->fetchAll("SELECT id, name FROM companies WHERE is_deleted=0 ORDER BY name");
        $vendors = $db->fetchAll("SELECT id, name FROM vendors WHERE is_deleted=0 ORDER BY name");
        $saleItems = $db->fetchAll("SELECT id, sort_order, name FROM sale_items WHERE is_deleted=0 ORDER BY sort_order");
        
        $saleNumber = $invoice['sale_number'] ?: generateSaleNumber($invoice['request_date']);
        $isEdit = true;
        
        $pageTitle = '세금계산서 발행 요청 수정';
        ob_start();
        include __DIR__ . '/../views/taxinvoice/form.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    /**
     * 저장 (신규 등록 + 수정)
     */
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('?page=taxinvoice');
        if (!CSRF::verify()) redirect('?page=taxinvoice');
        
        $db = Database::getInstance();
        $isEdit = !empty(postParam('invoice_id'));
        
        try {
            $db->beginTransaction();
            
            $status = postParam('status', 'requested');
            $saleNumber = trim(postParam('sale_number', ''));
            if (empty($saleNumber)) {
                $saleNumber = generateSaleNumber(postParam('request_date', date('Y-m-d')));
            }
            
            $data = [
                'sale_number'    => $saleNumber,
                'request_date'   => postParam('request_date', date('Y-m-d')),
                'delivery_date'  => !empty(postParam('delivery_date')) ? postParam('delivery_date') : null,
                'company_id'     => (int)postParam('company_id'),
                'total_amount'   => (int)str_replace(',', '', postParam('total_amount', '0')),
                'vat_amount'     => (int)str_replace(',', '', postParam('vat_amount', '0')),
                'purchase_total_amount' => (int)str_replace(',', '', postParam('purchase_total_amount', '0')),
                'purchase_vat_amount'   => (int)str_replace(',', '', postParam('purchase_vat_amount', '0')),
                'status'         => $status,
                'pending_reason' => ($status === 'pending') ? trim(postParam('pending_reason', '')) : null,
                'user_id'        => Session::getUserId(),
            ];
            
            if ($isEdit) {
                $invoiceId = (int)postParam('invoice_id');
                $old = $db->fetch("SELECT * FROM tax_invoices WHERE id=?", [$invoiceId]);
                if (!$old || $old['is_deleted']) { redirect('?page=taxinvoice'); }
                
                // 완료 건은 수정 불가
                if ($old['status'] === 'completed') {
                    Session::set('flash_message', '완료된 세금계산서는 수정할 수 없습니다.');
                    Session::set('flash_type', 'danger');
                    redirect('?page=taxinvoice');
                }
                
                if (!Auth::hasRole(['admin'])) { redirect('?page=taxinvoice'); }
                
                $db->update('tax_invoices', $data, 'id=?', [$invoiceId]);
                // 기존 상세 삭제 후 재등록
                $db->delete('tax_invoice_details', 'tax_invoice_id=?', [$invoiceId]);
                // 기존 매입 상세 삭제
                $existPurchases = $db->fetchAll("SELECT id FROM tax_invoice_purchases WHERE tax_invoice_id=?", [$invoiceId]);
                foreach ($existPurchases as $ep) {
                    $db->delete('tax_invoice_purchase_details', 'tax_invoice_purchase_id=?', [$ep['id']]);
                }
                $db->delete('tax_invoice_purchases', 'tax_invoice_id=?', [$invoiceId]);
                
                AuditLog::log('UPDATE', 'tax_invoices', $invoiceId, $old, $data);
            } else {
                $invoiceId = $db->insert('tax_invoices', $data);
                AuditLog::log('INSERT', 'tax_invoices', $invoiceId, null, $data);
            }
            
            // === 판매 제품 상세 라인 저장 ===
            $pNames = $_POST['ti_product_name'] ?? [];
            $pItemIds = $_POST['ti_sale_item_id'] ?? [];
            $pPrices = $_POST['ti_unit_price'] ?? [];
            $pQtys  = $_POST['ti_quantity'] ?? [];
            
            for ($i = 0; $i < count($pNames); $i++) {
                if (empty(trim($pNames[$i]))) continue;
                $price = (int)str_replace(',', '', $pPrices[$i] ?? '0');
                $qty   = (int)($pQtys[$i] ?? 1);
                $itemId = !empty($pItemIds[$i]) ? (int)$pItemIds[$i] : null;
                $db->insert('tax_invoice_details', [
                    'tax_invoice_id' => $invoiceId,
                    'product_name'   => trim($pNames[$i]),
                    'sale_item_id'   => $itemId,
                    'quantity'       => $qty,
                    'unit_price'     => $price,
                    'subtotal'       => $price * $qty,
                    'sort_order'     => $i,
                ]);
            }
            
            // === 매입 정보 저장 ===
            $tipDates = $_POST['tip_date'] ?? [];
            $tipTotals = $_POST['tip_total'] ?? [];
            $tipVats = $_POST['tip_vat'] ?? [];
            $tipProducts = $_POST['tip_product_name'] ?? [];
            $tipPrices = $_POST['tip_unit_price'] ?? [];
            $tipQtys = $_POST['tip_quantity'] ?? [];
            $tipVendors = $_POST['tip_vendor_id'] ?? [];
            
            foreach ($tipDates as $bi => $purchDate) {
                if (empty($purchDate)) continue;
                $blockTotal = (int)str_replace(',', '', $tipTotals[$bi] ?? '0');
                $blockVat = (int)str_replace(',', '', $tipVats[$bi] ?? '0');
                
                $purchId = $db->insert('tax_invoice_purchases', [
                    'tax_invoice_id' => $invoiceId,
                    'purchase_date'  => $purchDate,
                    'total_amount'   => $blockTotal,
                    'vat_amount'     => $blockVat,
                ]);
                
                $products = $tipProducts[$bi] ?? [];
                $prices = $tipPrices[$bi] ?? [];
                $qtys = $tipQtys[$bi] ?? [];
                $vendors = $tipVendors[$bi] ?? [];
                
                for ($j = 0; $j < count($products); $j++) {
                    if (empty(trim($products[$j] ?? ''))) continue;
                    $up = (int)str_replace(',', '', $prices[$j] ?? '0');
                    $q = (int)($qtys[$j] ?? 1);
                    $vid = !empty($vendors[$j]) ? (int)$vendors[$j] : null;
                    $db->insert('tax_invoice_purchase_details', [
                        'tax_invoice_purchase_id' => $purchId,
                        'product_name' => trim($products[$j]),
                        'vendor_id'    => $vid,
                        'unit_price'   => $up,
                        'quantity'     => $q,
                        'subtotal'     => $up * $q,
                        'sort_order'   => $j,
                    ]);
                }
            }
            
            // === 완료 시 매출/매입 관리 리스트에 자동 이관 ===
            if ($status === 'completed') {
                $this->transferToSales($db, $invoiceId, $data);
            }
            
            $db->commit();
            Session::set('flash_message', $isEdit ? '세금계산서 요청이 수정되었습니다.' : '세금계산서 발행 요청이 등록되었습니다.');
            if ($status === 'completed') {
                Session::set('flash_message', ($isEdit ? '세금계산서 요청이 수정' : '세금계산서 발행 요청이 등록') . '되었으며, 매출/매입 관리에 자동 등록되었습니다.');
            }
            Session::set('flash_type', 'success');
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("TaxInvoice save error: " . $e->getMessage());
            Session::set('flash_message', '저장 중 오류: ' . $e->getMessage());
            Session::set('flash_type', 'danger');
        }
        redirect('?page=taxinvoice');
    }
    
    /**
     * 완료 시 매출/매입 관리에 자동 이관
     */
    private function transferToSales($db, $invoiceId, $invoiceData) {
        // 이미 이관된 건인지 확인
        $existing = $db->fetch("SELECT linked_sale_id FROM tax_invoices WHERE id=?", [$invoiceId]);
        if (!empty($existing['linked_sale_id'])) {
            return; // 이미 이관됨 — 중복 방지
        }
        
        // 매출번호 생성
        $saleNumber = generateSaleNumber($invoiceData['request_date']);
        
        // 1. sales 테이블 INSERT
        $saleId = $db->insert('sales', [
            'sale_number'   => $saleNumber,
            'sale_date'     => $invoiceData['request_date'],
            'delivery_date' => $invoiceData['delivery_date'],
            'company_id'    => $invoiceData['company_id'] ?: null,
            'total_amount'  => $invoiceData['total_amount'],
            'vat_amount'    => $invoiceData['vat_amount'],
            'user_id'       => $invoiceData['user_id'],
        ]);
        
        // 2. sale_details 이관
        $details = $db->fetchAll(
            "SELECT * FROM tax_invoice_details WHERE tax_invoice_id=? ORDER BY sort_order", [$invoiceId]
        );
        foreach ($details as $i => $d) {
            $db->insert('sale_details', [
                'sale_id'      => $saleId,
                'product_name' => $d['product_name'],
                'sale_item_id' => $d['sale_item_id'],
                'unit_price'   => $d['unit_price'],
                'quantity'     => $d['quantity'],
                'subtotal'     => $d['subtotal'],
                'sort_order'   => $i,
            ]);
        }
        
        // 3. purchases 이관
        $tiPurchases = $db->fetchAll(
            "SELECT * FROM tax_invoice_purchases WHERE tax_invoice_id=? ORDER BY id", [$invoiceId]
        );
        foreach ($tiPurchases as $tp) {
            $purchNumber = generatePurchaseNumber($tp['purchase_date']);
            
            // 매입 상세에서 첫번째 vendor_id 추출
            $firstVendor = $db->fetch(
                "SELECT vendor_id FROM tax_invoice_purchase_details WHERE tax_invoice_purchase_id=? AND vendor_id IS NOT NULL LIMIT 1",
                [$tp['id']]
            );
            $vendorId = $firstVendor ? $firstVendor['vendor_id'] : null;
            
            // vendor_id가 없으면 기본 업체 사용 (FK 제약 때문)
            if (!$vendorId) {
                $defaultVendor = $db->fetch("SELECT id FROM vendors WHERE is_deleted=0 LIMIT 1");
                $vendorId = $defaultVendor ? $defaultVendor['id'] : 1;
            }
            
            $purchaseId = $db->insert('purchases', [
                'purchase_number' => $purchNumber,
                'sale_id'         => $saleId,
                'purchase_date'   => $tp['purchase_date'],
                'vendor_id'       => $vendorId,
                'total_amount'    => $tp['total_amount'],
                'vat_amount'      => $tp['vat_amount'],
                'user_id'         => $invoiceData['user_id'],
            ]);
            
            // 4. purchase_details 이관
            $tpDetails = $db->fetchAll(
                "SELECT * FROM tax_invoice_purchase_details WHERE tax_invoice_purchase_id=? ORDER BY sort_order",
                [$tp['id']]
            );
            foreach ($tpDetails as $j => $td) {
                $db->insert('purchase_details', [
                    'purchase_id'  => $purchaseId,
                    'product_name' => $td['product_name'],
                    'vendor_id'    => $td['vendor_id'],
                    'unit_price'   => $td['unit_price'],
                    'quantity'     => $td['quantity'],
                    'subtotal'     => $td['subtotal'],
                    'sort_order'   => $j,
                ]);
            }
        }
        
        // 5. 역참조 저장
        $db->update('tax_invoices', ['linked_sale_id' => $saleId], 'id=?', [$invoiceId]);
        
        AuditLog::log('INSERT', 'sales', $saleId, null, [
            'source' => 'tax_invoice',
            'tax_invoice_id' => $invoiceId,
        ], '세금계산서 완료 → 매출/매입 자동 이관');
    }
    
    /**
     * 상세 데이터 (AJAX JSON) - 보기용
     */
    public function getDetail() {
        $db = Database::getInstance();
        $id = (int)getParam('id');
        
        $invoice = $db->fetch(
            "SELECT t.*, c.name as company_name FROM tax_invoices t 
             LEFT JOIN companies c ON t.company_id=c.id
             WHERE t.id=? AND t.is_deleted=0", [$id]
        );
        if (!$invoice) { jsonResponse(['error' => 'Not found'], 404); }
        
        $details = $db->fetchAll(
            "SELECT d.*, si.sort_order as item_sort, si.name as item_name 
             FROM tax_invoice_details d
             LEFT JOIN sale_items si ON d.sale_item_id=si.id
             WHERE d.tax_invoice_id=? ORDER BY d.sort_order", [$id]
        );
        
        // 매입 정보
        $purchases = $db->fetchAll(
            "SELECT * FROM tax_invoice_purchases WHERE tax_invoice_id=? ORDER BY id", [$id]
        );
        foreach ($purchases as &$p) {
            $p['details'] = $db->fetchAll(
                "SELECT pd.*, v.name as vendor_name 
                 FROM tax_invoice_purchase_details pd
                 LEFT JOIN vendors v ON pd.vendor_id=v.id
                 WHERE pd.tax_invoice_purchase_id=? ORDER BY pd.sort_order",
                [$p['id']]
            );
        }
        
        jsonResponse(['invoice' => $invoice, 'details' => $details, 'purchases' => $purchases]);
    }
    
    /**
     * 삭제 (Soft Delete, admin만)
     */
    public function delete() {
        if (!CSRF::verify($_GET['token'] ?? '')) redirect('?page=taxinvoice');
        if (!Auth::hasRole(['admin'])) redirect('?page=taxinvoice');
        
        $db = Database::getInstance();
        $id = (int)getParam('id');
        $old = $db->fetch("SELECT * FROM tax_invoices WHERE id=?", [$id]);
        
        if ($old) {
            $db->update('tax_invoices', [
                'is_deleted' => 1, 
                'deleted_at' => date('Y-m-d H:i:s')
            ], 'id=?', [$id]);
            AuditLog::log('DELETE', 'tax_invoices', $id, $old, null, '세금계산서 요청 삭제');
            Session::set('flash_message', '삭제되었습니다.');
            Session::set('flash_type', 'success');
        }
        redirect('?page=taxinvoice');
    }
    
    /**
     * 매출번호 자동 생성 AJAX
     */
    public function generateNumber() {
        $date = getParam('date', date('Y-m-d'));
        $saleNumber = generateSaleNumber($date);
        jsonResponse(['sale_number' => $saleNumber]);
    }
    
    /**
     * 발행 요청 건 CSV 다운로드
     */
    public function exportRequested() {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT t.*, c.name as company_name
             FROM tax_invoices t
             LEFT JOIN companies c ON t.company_id=c.id
             WHERE t.is_deleted=0 AND t.status IN ('requested','pending')
             ORDER BY t.request_date DESC, t.id DESC"
        );
        
        AuditLog::log('EXPORT', 'tax_invoices', null, null, null, '세금계산서 발행요청 CSV 다운로드');
        
        $statusMap = ['requested' => '요청', 'pending' => '보류', 'completed' => '완료'];
        $csvRows = [];
        foreach ($rows as $i => $r) {
            $csvRows[] = [
                $i + 1, $r['sale_number'] ?? '-', $r['request_date'], 
                $r['delivery_date'] ?? '-', $r['company_name'],
                number_format($r['total_amount']), number_format($r['vat_amount']),
                number_format($r['purchase_total_amount']), number_format($r['purchase_vat_amount']),
                $statusMap[$r['status']] ?? $r['status']
            ];
        }
        csvExport('세금계산서_발행요청_' . date('Ymd') . '.csv',
            ['순위', '매출번호', '매출일자', '출고일자', '업체명', '매출총액', '부가세', '매입총액', '매입부가세', '처리상태'], $csvRows);
    }
    
    /**
     * 발행 완료 건 CSV 다운로드
     */
    public function exportCompleted() {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT t.*, c.name as company_name
             FROM tax_invoices t
             LEFT JOIN companies c ON t.company_id=c.id
             WHERE t.is_deleted=0 AND t.status='completed'
             ORDER BY t.request_date DESC, t.id DESC"
        );
        
        AuditLog::log('EXPORT', 'tax_invoices', null, null, null, '세금계산서 발행완료 CSV 다운로드');
        
        $csvRows = [];
        foreach ($rows as $i => $r) {
            $csvRows[] = [
                $i + 1, $r['sale_number'] ?? '-', $r['request_date'],
                $r['delivery_date'] ?? '-', $r['company_name'],
                number_format($r['total_amount']), number_format($r['vat_amount']),
                number_format($r['purchase_total_amount']), number_format($r['purchase_vat_amount']),
                '완료'
            ];
        }
        csvExport('세금계산서_발행완료_' . date('Ymd') . '.csv',
            ['순위', '매출번호', '매출일자', '출고일자', '업체명', '매출총액', '부가세', '매입총액', '매입부가세', '처리상태'], $csvRows);
    }
}
