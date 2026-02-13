<?php
$statusLabels = ['requested' => '요청', 'pending' => '보류', 'completed' => '완료'];
$statusColors = ['requested' => '#2E7D4F', 'pending' => '#E89B23', 'completed' => '#0077B6'];
$statusBg     = ['requested' => '#E8F5E9', 'pending' => '#FFF3E0', 'completed' => '#E0F2FE'];
$csrfToken = CSRF::generate();
$flashMsg = Session::get('flash_message');
$flashType = Session::get('flash_type', 'success');
if ($flashMsg) { Session::remove('flash_message'); Session::remove('flash_type'); }
?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= e($flashType) ?>" style="margin-bottom:16px;">
    <i class="fas fa-<?= $flashType === 'danger' ? 'exclamation-circle' : 'check-circle' ?>"></i>
    <?= e($flashMsg) ?>
</div>
<?php endif; ?>

<!-- 상단: 제목 + 등록 버튼 -->
<div class="d-flex justify-between align-center mb-2">
    <h2 style="font-size:20px;font-weight:700;color:var(--text);margin:0;">
        <i class="fas fa-file-invoice" style="color:var(--accent);"></i> 세금계산서 발행 요청 관리
    </h2>
    <button class="btn btn-primary" onclick="openCreatePopup()">
        <i class="fas fa-plus"></i> 발행 요청 등록
    </button>
</div>

<!-- 집계 카드 -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
    <div class="stat-card" style="border-left:4px solid #2E7D4F;">
        <div class="stat-label"><i class="fas fa-paper-plane" style="color:#2E7D4F;"></i> 발행 요청</div>
        <div class="stat-value" style="color:#2E7D4F;"><?= number_format($reqOnlyCount) ?></div>
        <div class="stat-sub">건</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #E89B23;">
        <div class="stat-label"><i class="fas fa-pause-circle" style="color:#E89B23;"></i> 보류</div>
        <div class="stat-value" style="color:#E89B23;"><?= number_format($pendingCount) ?></div>
        <div class="stat-sub">건</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #0077B6;">
        <div class="stat-label"><i class="fas fa-check-circle" style="color:#0077B6;"></i> 발행 완료</div>
        <div class="stat-value" style="color:#0077B6;"><?= number_format($compCount) ?></div>
        <div class="stat-sub">건</div>
    </div>
</div>

<!-- ===== 발행 요청 리스트 ===== -->
<div class="card mb-2">
    <div class="card-header">
        <h3><i class="fas fa-list-alt" style="color:#2E7D4F;"></i> 발행 요청 건 (요청/보류)</h3>
        <a href="?page=taxinvoice&action=exportRequested" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV 다운로드</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>요청일자</th>
                        <th>매출업체</th>
                        <th>프로젝트</th>
                        <th class="text-right">총액</th>
                        <th style="width:80px;text-align:center;">처리상태</th>
                        <th style="width:160px;">보류 사유</th>
                        <th style="width:80px;text-align:center;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requestList)): ?>
                    <tr><td colspan="8" class="text-center" style="padding:30px;color:var(--text-muted);">발행 요청 건이 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($requestList as $idx => $r): ?>
                    <tr>
                        <td><?= $reqPag['offset'] + $idx + 1 ?></td>
                        <td style="white-space:nowrap;"><?= e($r['request_date']) ?></td>
                        <td><strong><?= e($r['company_name']) ?></strong></td>
                        <td><?= e($r['project_name']) ?></td>
                        <td class="money"><?= formatMoney($r['total_amount']) ?>원</td>
                        <td style="text-align:center;">
                            <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;
                                color:<?= $statusColors[$r['status']] ?>;background:<?= $statusBg[$r['status']] ?>;">
                                <?= $statusLabels[$r['status']] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'pending' && !empty($r['pending_reason'])): ?>
                            <div style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#E89B23;cursor:help;"
                                 title="<?= e($r['pending_reason']) ?>">
                                <?= e($r['pending_reason']) ?>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--text-muted);font-size:12px;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <button class="btn btn-outline btn-sm" onclick="openViewPopup(<?= $r['id'] ?>)" title="보기"><i class="fas fa-eye"></i></button>
                            <?php if ($isAdmin): ?>
                            <button class="btn btn-outline btn-sm" onclick="openEditPopup(<?= $r['id'] ?>)" title="편집"><i class="fas fa-edit"></i></button>
                            <a href="?page=taxinvoice&action=delete&id=<?= $r['id'] ?>&token=<?= e($csrfToken) ?>" 
                               class="btn btn-danger btn-sm" onclick="return confirm('삭제하시겠습니까?')" title="삭제"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($reqPag['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($reqPag['current'] > 1): ?>
            <a href="?page=taxinvoice&rp=<?= $reqPag['current']-1 ?>&cp=<?= $compPage ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $reqPag['current']-3); $i <= min($reqPag['total_pages'], $reqPag['current']+3); $i++): ?>
            <a href="?page=taxinvoice&rp=<?= $i ?>&cp=<?= $compPage ?>" class="<?= $i == $reqPag['current'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($reqPag['current'] < $reqPag['total_pages']): ?>
            <a href="?page=taxinvoice&rp=<?= $reqPag['current']+1 ?>&cp=<?= $compPage ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== 발행 완료 리스트 ===== -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-check-double" style="color:#0077B6;"></i> 발행 완료 건</h3>
        <a href="?page=taxinvoice&action=exportCompleted" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV 다운로드</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>요청일자</th>
                        <th>매출업체</th>
                        <th>프로젝트</th>
                        <th class="text-right">총액</th>
                        <th style="width:80px;text-align:center;">처리상태</th>
                        <?php if ($isAdmin): ?><th style="width:60px;text-align:center;">관리</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($completedList)): ?>
                    <tr><td colspan="<?= $isAdmin ? 7 : 6 ?>" class="text-center" style="padding:30px;color:var(--text-muted);">발행 완료 건이 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($completedList as $idx => $r): ?>
                    <tr>
                        <td><?= $compPag['offset'] + $idx + 1 ?></td>
                        <td style="white-space:nowrap;"><?= e($r['request_date']) ?></td>
                        <td><strong><?= e($r['company_name']) ?></strong></td>
                        <td><?= e($r['project_name']) ?></td>
                        <td class="money"><?= formatMoney($r['total_amount']) ?>원</td>
                        <td style="text-align:center;">
                            <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;
                                color:#0077B6;background:#E0F2FE;">완료</span>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td style="text-align:center;white-space:nowrap;">
                            <a href="?page=taxinvoice&action=delete&id=<?= $r['id'] ?>&token=<?= e($csrfToken) ?>" 
                               class="btn btn-danger btn-sm" onclick="return confirm('삭제하시겠습니까?')" title="삭제"><i class="fas fa-trash"></i></a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($compPag['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($compPag['current'] > 1): ?>
            <a href="?page=taxinvoice&rp=<?= $reqPage ?>&cp=<?= $compPag['current']-1 ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $compPag['current']-3); $i <= min($compPag['total_pages'], $compPag['current']+3); $i++): ?>
            <a href="?page=taxinvoice&rp=<?= $reqPage ?>&cp=<?= $i ?>" class="<?= $i == $compPag['current'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($compPag['current'] < $compPag['total_pages']): ?>
            <a href="?page=taxinvoice&rp=<?= $reqPage ?>&cp=<?= $compPag['current']+1 ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== 팝업 오버레이 ===== -->
<div id="tiPopupOverlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45);z-index:9998;"></div>

<!-- ===== 발행 요청 등록/수정/보기 팝업 ===== -->
<div id="tiPopup" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
    width:780px;max-width:95vw;max-height:90vh;overflow-y:auto;background:#fff;border-radius:12px;
    box-shadow:0 20px 60px rgba(0,0,0,.3);z-index:9999;">
    <form method="POST" action="?page=taxinvoice&action=save" id="tiForm">
        <?= CSRF::field() ?>
        <input type="hidden" name="invoice_id" id="ti_invoice_id" value="">
        
        <!-- 팝업 헤더 -->
        <div style="padding:20px 24px;border-bottom:2px solid var(--accent);display:flex;justify-content:space-between;align-items:center;">
            <h3 id="tiPopupTitle" style="margin:0;font-size:18px;font-weight:700;color:var(--text);">
                <i class="fas fa-file-invoice" style="color:var(--accent);"></i> 세금계산서 발행 요청 등록
            </h3>
            <button type="button" onclick="closePopup()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);padding:4px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- 팝업 본문 -->
        <div style="padding:20px 24px;">
            <!-- 요청일자 / 업체명 -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">요청일자 <span class="text-danger">*</span></label>
                    <input type="text" name="request_date" id="ti_request_date" class="form-control ti-datepicker" required>
                </div>
                <div class="form-group">
                    <label class="form-label">업체명 <span class="text-danger">*</span></label>
                    <select name="company_id" id="ti_company_id" class="form-control" required>
                        <option value="">-- 매출업체 선택 --</option>
                        <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- 프로젝트 -->
            <div class="form-group">
                <label class="form-label">프로젝트</label>
                <input type="text" name="project_name" id="ti_project_name" class="form-control" placeholder="프로젝트명 직접 입력">
            </div>
            
            <!-- 제품 라인 아이템 (다중) -->
            <div class="form-label" style="margin-top:12px;margin-bottom:6px;">
                <i class="fas fa-box" style="color:var(--accent);"></i> 제품 내역
            </div>
            <div class="ti-line-item-header">
                <span>제품명</span><span>수량</span><span>단가</span><span>소계</span><span></span>
            </div>
            <div id="ti-lines" class="line-items">
                <div class="ti-line-item">
                    <input type="text" name="ti_product_name[]" class="form-control" placeholder="제품명 입력" required>
                    <input type="number" name="ti_quantity[]" class="form-control" value="1" min="1" oninput="calcTiLine(this)">
                    <input type="text" name="ti_unit_price[]" class="form-control input-money" placeholder="단가" oninput="calcTiLine(this)">
                    <input type="text" class="form-control input-money ti-subtotal" readonly value="0">
                    <button type="button" class="btn-remove" onclick="removeTiLine(this)"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="tiAddLineBtn" onclick="addTiLine()" style="margin-top:6px;">
                <i class="fas fa-plus"></i> 제품 추가
            </button>
            
            <!-- 총액 / 부가세 -->
            <div style="margin-top:16px;border-top:2px solid var(--border);padding-top:12px;">
                <div class="total-row">
                    <span class="total-label"><strong>총액</strong></span>
                    <span class="total-value" id="ti-total-display" style="font-size:20px;">0</span>
                </div>
                <input type="hidden" name="total_amount" id="ti_total_amount" value="0">
                <div class="total-row" style="margin-top:4px;">
                    <span class="total-label">부가세 (10%)</span>
                    <span class="total-value" id="ti-vat-display" style="font-size:14px;color:var(--text-light);">0</span>
                </div>
                <input type="hidden" name="vat_amount" id="ti_vat_amount" value="0">
            </div>
            
            <!-- 처리 상태 -->
            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">처리 상태</label>
                <div class="d-flex gap-2">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 16px;border:2px solid #2E7D4F;border-radius:8px;font-weight:600;color:#2E7D4F;background:#E8F5E9;">
                        <input type="radio" name="status" value="requested" checked onchange="togglePendingReason()"> 요청
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 16px;border:2px solid #E89B23;border-radius:8px;font-weight:600;color:#E89B23;background:#FFF3E0;">
                        <input type="radio" name="status" value="pending" onchange="togglePendingReason()"> 보류
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:8px 16px;border:2px solid #0077B6;border-radius:8px;font-weight:600;color:#0077B6;background:#E0F2FE;">
                        <input type="radio" name="status" value="completed" onchange="togglePendingReason()"> 완료
                    </label>
                </div>
            </div>
            
            <!-- 보류 사유 (보류 선택시만 활성화) -->
            <div class="form-group" id="pendingReasonWrap" style="display:none;margin-top:8px;">
                <label class="form-label" style="color:#E89B23;"><i class="fas fa-exclamation-triangle" style="color:#E89B23;"></i> 보류 사유 <span class="text-danger">*</span></label>
                <textarea name="pending_reason" id="ti_pending_reason" class="form-control" rows="3" 
                    placeholder="보류 사유를 입력하세요..." style="border-color:#E89B23;resize:vertical;"></textarea>
            </div>
        </div>
        
        <!-- 팝업 하단 버튼 -->
        <div id="tiPopupFooter" style="padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;background:#FAFAFA;border-radius:0 0 12px 12px;">
            <button type="button" class="btn btn-outline" onclick="closePopup()"><i class="fas fa-times"></i> 취소</button>
            <button type="submit" class="btn btn-primary" id="tiSubmitBtn"><i class="fas fa-save"></i> 등록</button>
        </div>
    </form>
</div>

<?php
$companiesJson = json_encode($companies, JSON_UNESCAPED_UNICODE);
$pageScript = <<<JS

var isViewMode = false; // 보기 전용 모드 플래그

// ===== 보류 사유 토글 =====
function togglePendingReason() {
    var status = document.querySelector('input[name="status"]:checked');
    var wrap = document.getElementById('pendingReasonWrap');
    var textarea = document.getElementById('ti_pending_reason');
    if (status && status.value === 'pending') {
        wrap.style.display = 'block';
        textarea.required = true;
    } else {
        wrap.style.display = 'none';
        textarea.required = false;
    }
}

// ===== 팝업 열기/닫기 =====
function openCreatePopup() {
    isViewMode = false;
    document.getElementById('ti_invoice_id').value = '';
    document.getElementById('tiPopupTitle').innerHTML = '<i class="fas fa-file-invoice" style="color:var(--accent);"></i> 세금계산서 발행 요청 등록';
    document.getElementById('tiSubmitBtn').innerHTML = '<i class="fas fa-save"></i> 등록';
    document.getElementById('tiForm').reset();
    
    // 제품 라인 초기화 (1줄만)
    var lines = document.getElementById('ti-lines');
    lines.innerHTML = '<div class="ti-line-item">' +
        '<input type="text" name="ti_product_name[]" class="form-control" placeholder="제품명 입력" required>' +
        '<input type="number" name="ti_quantity[]" class="form-control" value="1" min="1" oninput="calcTiLine(this)">' +
        '<input type="text" name="ti_unit_price[]" class="form-control input-money" placeholder="단가" oninput="calcTiLine(this)">' +
        '<input type="text" class="form-control input-money ti-subtotal" readonly value="0">' +
        '<button type="button" class="btn-remove" onclick="removeTiLine(this)"><i class="fas fa-times"></i></button></div>';
    
    document.getElementById('ti-total-display').textContent = '0';
    document.getElementById('ti-vat-display').textContent = '0';
    document.getElementById('ti_pending_reason').value = '';
    
    setFormEditable(true);
    togglePendingReason();
    showPopup();
    initTiDatepicker();
    initMoneyInputs();
}

function openEditPopup(id) {
    isViewMode = false;
    loadInvoiceData(id, false);
}

function openViewPopup(id) {
    isViewMode = true;
    loadInvoiceData(id, true);
}

function loadInvoiceData(id, readOnly) {
    fetch('?page=taxinvoice&action=getDetail&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { alert(data.error); return; }
            var inv = data.invoice;
            var det = data.details;
            
            document.getElementById('ti_invoice_id').value = inv.id;
            
            if (readOnly) {
                document.getElementById('tiPopupTitle').innerHTML = '<i class="fas fa-eye" style="color:var(--accent);"></i> 세금계산서 요청 상세보기 (#' + inv.id + ')';
            } else {
                document.getElementById('tiPopupTitle').innerHTML = '<i class="fas fa-edit" style="color:var(--accent);"></i> 세금계산서 요청 수정 (#' + inv.id + ')';
                document.getElementById('tiSubmitBtn').innerHTML = '<i class="fas fa-save"></i> 수정 저장';
            }
            
            document.getElementById('ti_company_id').value = inv.company_id;
            document.getElementById('ti_project_name').value = inv.project_name;
            
            // 상태 라디오 설정
            var radios = document.querySelectorAll('input[name="status"]');
            radios.forEach(function(r) { r.checked = (r.value === inv.status); });
            
            // 보류 사유 복원
            document.getElementById('ti_pending_reason').value = inv.pending_reason || '';
            togglePendingReason();
            
            // 제품 라인 복원
            var lines = document.getElementById('ti-lines');
            lines.innerHTML = '';
            if (det.length === 0) det = [{ product_name: '', quantity: 1, unit_price: 0, subtotal: 0 }];
            det.forEach(function(d) {
                lines.innerHTML += '<div class="ti-line-item">' +
                    '<input type="text" name="ti_product_name[]" class="form-control" placeholder="제품명 입력" required value="' + escHtml(d.product_name) + '">' +
                    '<input type="number" name="ti_quantity[]" class="form-control" value="' + d.quantity + '" min="1" oninput="calcTiLine(this)">' +
                    '<input type="text" name="ti_unit_price[]" class="form-control input-money" value="' + fmtNum(d.unit_price) + '" oninput="calcTiLine(this)">' +
                    '<input type="text" class="form-control input-money ti-subtotal" readonly value="' + fmtNum(d.subtotal) + '">' +
                    '<button type="button" class="btn-remove" onclick="removeTiLine(this)"><i class="fas fa-times"></i></button></div>';
            });
            
            setFormEditable(!readOnly);
            showPopup();
            initTiDatepicker(inv.request_date);
            initMoneyInputs();
            calcTiTotal();
        })
        .catch(function(err) { alert('데이터 로드 실패: ' + err); });
}

// ===== 읽기 전용 모드 전환 =====
function setFormEditable(editable) {
    var form = document.getElementById('tiForm');
    var fields = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
    var footer = document.getElementById('tiPopupFooter');
    var addLineBtn = document.getElementById('tiAddLineBtn');
    var removeBtns = form.querySelectorAll('.btn-remove');
    
    fields.forEach(function(f) {
        if (editable) {
            f.removeAttribute('disabled');
        } else {
            f.setAttribute('disabled', 'disabled');
        }
    });
    
    // 제품 추가/삭제 버튼
    if (addLineBtn) addLineBtn.style.display = editable ? '' : 'none';
    removeBtns.forEach(function(b) { b.style.display = editable ? '' : 'none'; });
    
    // 하단 버튼 영역
    if (editable) {
        footer.innerHTML = '<button type="button" class="btn btn-outline" onclick="closePopup()"><i class="fas fa-times"></i> 취소</button>' +
            '<button type="submit" class="btn btn-primary" id="tiSubmitBtn"><i class="fas fa-save"></i> 등록</button>';
    } else {
        footer.innerHTML = '<button type="button" class="btn btn-outline" onclick="closePopup()"><i class="fas fa-times"></i> 닫기</button>';
    }
}

function showPopup() {
    document.getElementById('tiPopupOverlay').style.display = 'block';
    document.getElementById('tiPopup').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closePopup() {
    document.getElementById('tiPopupOverlay').style.display = 'none';
    document.getElementById('tiPopup').style.display = 'none';
    document.body.style.overflow = '';
    isViewMode = false;
}

// 오버레이 클릭 시 닫기
document.getElementById('tiPopupOverlay').addEventListener('click', closePopup);

// ESC 키로 닫기
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closePopup(); });

// ===== 날짜 picker =====
var tiDatepickerInst = null;
function initTiDatepicker(defaultVal) {
    if (tiDatepickerInst) tiDatepickerInst.destroy();
    tiDatepickerInst = flatpickr('#ti_request_date', {
        locale: 'ko',
        dateFormat: 'Y-m-d',
        defaultDate: defaultVal || 'today',
        allowInput: true
    });
}

// ===== 제품 라인 추가/삭제/계산 =====
function addTiLine() {
    if (isViewMode) return;
    var html = '<div class="ti-line-item">' +
        '<input type="text" name="ti_product_name[]" class="form-control" placeholder="제품명 입력" required>' +
        '<input type="number" name="ti_quantity[]" class="form-control" value="1" min="1" oninput="calcTiLine(this)">' +
        '<input type="text" name="ti_unit_price[]" class="form-control input-money" placeholder="단가" oninput="calcTiLine(this)">' +
        '<input type="text" class="form-control input-money ti-subtotal" readonly value="0">' +
        '<button type="button" class="btn-remove" onclick="removeTiLine(this)"><i class="fas fa-times"></i></button></div>';
    document.getElementById('ti-lines').insertAdjacentHTML('beforeend', html);
    initMoneyInputs();
}

function removeTiLine(btn) {
    if (isViewMode) return;
    var container = btn.closest('.line-items');
    if (container.querySelectorAll('.ti-line-item').length > 1) {
        btn.closest('.ti-line-item').remove();
        calcTiTotal();
    }
}

function calcTiLine(el) {
    var row = el.closest('.ti-line-item');
    var price = parseNumber(row.querySelector('[name="ti_unit_price[]"]').value);
    var qty = parseInt(row.querySelector('[name="ti_quantity[]"]').value) || 1;
    row.querySelector('.ti-subtotal').value = formatNumber(price * qty);
    calcTiTotal();
}

function calcTiTotal() {
    var total = 0;
    document.querySelectorAll('.ti-subtotal').forEach(function(el) {
        total += parseNumber(el.value);
    });
    document.getElementById('ti-total-display').textContent = formatNumber(total);
    document.getElementById('ti_total_amount').value = total;
    var vat = Math.floor(total * 0.1);
    document.getElementById('ti-vat-display').textContent = formatNumber(vat);
    document.getElementById('ti_vat_amount').value = vat;
}

// 유틸리티
function fmtNum(v) { return new Intl.NumberFormat('ko-KR').format(parseInt(v) || 0); }
function escHtml(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s || '')); return d.innerHTML; }
JS;
?>
