<?php
/**
 * AltNET Ecount ERP - 세금계산서 발행 요청 관리 Controller
 */
class TaxInvoiceController {
    
    public function index() {
        $db = Database::getInstance();
        
        // 발행 요청 탭 페이지
        $reqPage = max(1, (int)getParam('rp', 1));
        // 발행 완료 탭 페이지
        $compPage = max(1, (int)getParam('cp', 1));
        $perPage = 15;
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
        
        // 업체 목록 (팝업 폼용)
        $companies = $db->fetchAll("SELECT id, name FROM companies WHERE is_deleted=0 ORDER BY name");
        
        $pageTitle = '세금계산서 발행 요청';
        ob_start();
        include __DIR__ . '/../views/taxinvoice/index.php';
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
            $data = [
                'request_date'  => postParam('request_date', date('Y-m-d')),
                'company_id'    => (int)postParam('company_id'),
                'project_name'  => trim(postParam('project_name', '')),
                'total_amount'  => (int)str_replace(',', '', postParam('total_amount', '0')),
                'vat_amount'    => (int)str_replace(',', '', postParam('vat_amount', '0')),
                'status'        => $status,
                'pending_reason' => ($status === 'pending') ? trim(postParam('pending_reason', '')) : null,
                'user_id'       => Session::getUserId(),
            ];
            
            if ($isEdit) {
                $invoiceId = (int)postParam('invoice_id');
                $old = $db->fetch("SELECT * FROM tax_invoices WHERE id=?", [$invoiceId]);
                if (!$old || $old['is_deleted']) { redirect('?page=taxinvoice'); }
                
                // admin만 수정 가능
                if (!Auth::hasRole(['admin'])) { redirect('?page=taxinvoice'); }
                
                $db->update('tax_invoices', $data, 'id=?', [$invoiceId]);
                $db->delete('tax_invoice_details', 'tax_invoice_id=?', [$invoiceId]);
                AuditLog::log('UPDATE', 'tax_invoices', $invoiceId, $old, $data);
            } else {
                $invoiceId = $db->insert('tax_invoices', $data);
                AuditLog::log('INSERT', 'tax_invoices', $invoiceId, null, $data);
            }
            
            // 상세 라인 저장
            $pNames = $_POST['ti_product_name'] ?? [];
            $pQtys  = $_POST['ti_quantity'] ?? [];
            $pPrices = $_POST['ti_unit_price'] ?? [];
            
            for ($i = 0; $i < count($pNames); $i++) {
                if (empty(trim($pNames[$i]))) continue;
                $price = (int)str_replace(',', '', $pPrices[$i] ?? '0');
                $qty   = (int)($pQtys[$i] ?? 1);
                $db->insert('tax_invoice_details', [
                    'tax_invoice_id' => $invoiceId,
                    'product_name'   => trim($pNames[$i]),
                    'quantity'       => $qty,
                    'unit_price'     => $price,
                    'subtotal'       => $price * $qty,
                    'sort_order'     => $i,
                ]);
            }
            
            $db->commit();
            Session::set('flash_message', $isEdit ? '세금계산서 요청이 수정되었습니다.' : '세금계산서 발행 요청이 등록되었습니다.');
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
     * 수정 폼 데이터 (AJAX JSON)
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
            "SELECT * FROM tax_invoice_details WHERE tax_invoice_id=? ORDER BY sort_order", [$id]
        );
        
        jsonResponse(['invoice' => $invoice, 'details' => $details]);
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
     * 발행 요청 건 CSV 다운로드
     */
    public function exportRequested() {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT t.request_date, c.name as company_name, t.project_name, t.total_amount, t.vat_amount, t.status
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
                $i + 1, $r['request_date'], $r['company_name'], $r['project_name'],
                number_format($r['total_amount']), number_format($r['vat_amount']),
                $statusMap[$r['status']] ?? $r['status']
            ];
        }
        csvExport('세금계산서_발행요청_' . date('Ymd') . '.csv',
            ['순위', '요청일자', '업체명', '프로젝트', '총액', '부가세', '처리상태'], $csvRows);
    }
    
    /**
     * 발행 완료 건 CSV 다운로드
     */
    public function exportCompleted() {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT t.request_date, c.name as company_name, t.project_name, t.total_amount, t.vat_amount, t.status
             FROM tax_invoices t
             LEFT JOIN companies c ON t.company_id=c.id
             WHERE t.is_deleted=0 AND t.status='completed'
             ORDER BY t.request_date DESC, t.id DESC"
        );
        
        AuditLog::log('EXPORT', 'tax_invoices', null, null, null, '세금계산서 발행완료 CSV 다운로드');
        
        $csvRows = [];
        foreach ($rows as $i => $r) {
            $csvRows[] = [
                $i + 1, $r['request_date'], $r['company_name'], $r['project_name'],
                number_format($r['total_amount']), number_format($r['vat_amount']), '완료'
            ];
        }
        csvExport('세금계산서_발행완료_' . date('Ymd') . '.csv',
            ['순위', '요청일자', '업체명', '프로젝트', '총액', '부가세', '처리상태'], $csvRows);
    }
}
