<form method="POST" action="?page=taxinvoice&action=save" id="tiForm">
    <?= CSRF::field() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
    <?php endif; ?>
    
    <!-- Two Column Layout: Sales (Left) + Purchase (Right) -->
    <div class="sale-form-grid">
        
        <!-- ========== LEFT: 매출 정보 ========== -->
        <div class="sale-section">
            <div class="sale-section-header sales-header">
                <i class="fas fa-chart-line"></i> 매출 정보
            </div>
            <div class="sale-section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">매출번호</label>
                        <input type="text" name="sale_number" class="form-control" value="<?= e($saleNumber) ?>" readonly style="background:rgba(255,255,255,0.02);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">매출일자</label>
                        <input type="text" name="request_date" id="ti_sale_date" class="form-control datepicker" 
                               value="<?= e($invoice['request_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">출고일자</label>
                        <input type="text" name="delivery_date" id="ti_delivery_date" class="form-control datepicker" 
                               value="<?= e($invoice['delivery_date'] ?? '') ?>" placeholder="출고일자 선택">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">업체명</label>
                        <select name="company_id" id="ti_company_id" class="form-control">
                            <option value="">-- 업체 선택 --</option>
                            <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($invoice['company_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- 판매 제품 라인 -->
                <div class="form-label" style="margin-top:12px;">판매 제품</div>
                <div class="sale-line-header">
                    <span>제품명</span><span>제품코드</span><span>단가</span><span>수량</span><span>소계</span><span></span>
                </div>
                <div id="ti-sale-lines" class="line-items">
                    <?php if (!empty($invoiceDetails)): ?>
                        <?php foreach ($invoiceDetails as $idx => $d): ?>
                        <div class="sale-line-item">
                            <input type="text" name="ti_product_name[]" class="form-control" placeholder="제품명" value="<?= e($d['product_name']) ?>">
                            <select name="ti_sale_item_id[]" class="form-control">
                                <option value="">-- 선택 --</option>
                                <?php foreach ($saleItems as $si): ?>
                                <option value="<?= $si['id'] ?>" <?= ($d['sale_item_id'] ?? '') == $si['id'] ? 'selected' : '' ?>><?= e($si['sort_order'] . '.' . $si['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="ti_unit_price[]" class="form-control input-money" placeholder="단가" value="<?= formatMoney($d['unit_price']) ?>" oninput="calcTiSaleLine(this)">
                            <input type="number" name="ti_quantity[]" class="form-control" value="<?= $d['quantity'] ?>" min="1" oninput="calcTiSaleLine(this)">
                            <input type="text" class="form-control input-money ti-sale-subtotal" readonly value="<?= formatMoney($d['subtotal']) ?>">
                            <button type="button" class="btn-remove" onclick="removeTiSaleLine(this)"><i class="fas fa-times"></i></button>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="sale-line-item">
                            <input type="text" name="ti_product_name[]" class="form-control" placeholder="제품명">
                            <select name="ti_sale_item_id[]" class="form-control">
                                <option value="">-- 선택 --</option>
                                <?php foreach ($saleItems as $si): ?>
                                <option value="<?= $si['id'] ?>"><?= e($si['sort_order'] . '.' . $si['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="ti_unit_price[]" class="form-control input-money" placeholder="단가" oninput="calcTiSaleLine(this)">
                            <input type="number" name="ti_quantity[]" class="form-control" value="1" min="1" oninput="calcTiSaleLine(this)">
                            <input type="text" class="form-control input-money ti-sale-subtotal" readonly value="0">
                            <button type="button" class="btn-remove" onclick="removeTiSaleLine(this)"><i class="fas fa-times"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="addTiSaleLine()"><i class="fas fa-plus"></i> 제품 추가</button>
                
                <div style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.06);padding-top:12px;">
                    <div class="total-row">
                        <span class="total-label">매출 총액</span>
                        <span class="total-value" id="ti-sale-total-display">0</span>
                    </div>
                    <input type="hidden" name="total_amount" id="ti_total_amount" value="<?= $invoice['total_amount'] ?? 0 ?>">
                    <div class="total-row" style="font-size:smaller;">
                        <span class="total-label">부가세 (10%)</span>
                        <span class="total-value" id="ti-sale-vat-display" style="font-size:14px;color:var(--text-light);">0</span>
                    </div>
                    <input type="hidden" name="vat_amount" id="ti_vat_amount" value="<?= $invoice['vat_amount'] ?? 0 ?>">
                </div>
            </div>
        </div>
        
        <!-- ========== RIGHT: 매입 정보 ========== -->
        <div class="sale-section">
            <div class="sale-section-header purchase-header">
                <i class="fas fa-shopping-cart"></i> 매입 정보
                <button type="button" class="btn btn-outline btn-sm" style="margin-left:auto;" onclick="addTiPurchaseBlock()"><i class="fas fa-plus"></i> 매입 추가</button>
            </div>
            <div class="sale-section-body" id="ti-purchase-blocks">
                <?php if (!empty($invoicePurchases)): ?>
                    <?php foreach ($invoicePurchases as $pi => $p): ?>
                    <div class="purchase-block" style="border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:12px;margin-bottom:12px;background:rgba(245,158,11,0.04);">
                        <div class="d-flex justify-between align-center mb-1">
                            <strong style="color:var(--amber-glow);font-size:13px;">매입 #<?= $pi + 1 ?></strong>
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.purchase-block').remove();calcTiPurchaseTotals();">삭제</button>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">매입일자</label>
                                <input type="text" name="tip_date[]" class="form-control datepicker" value="<?= e($p['purchase_date']) ?>">
                            </div>
                        </div>
                        <div class="form-label" style="margin-top:8px;color:var(--amber-glow);font-weight:700;font-size:12px;"><i class="fas fa-box"></i> 매입 제품</div>
                        <div class="purch-line-header"><span>제품명</span><span>매입업체</span><span>단가</span><span>수량</span><span>소계</span><span></span></div>
                        <div class="purchase-lines line-items">
                            <?php foreach ($p['details'] as $pd): ?>
                            <div class="purch-line-item">
                                <input type="text" name="tip_product_name[<?= $pi ?>][]" class="form-control" value="<?= e($pd['product_name']) ?>">
                                <select name="tip_vendor_id[<?= $pi ?>][]" class="form-control">
                                    <option value="">-- 선택 --</option>
                                    <?php foreach ($vendors as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= ($pd['vendor_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="tip_unit_price[<?= $pi ?>][]" class="form-control input-money" value="<?= formatMoney($pd['unit_price']) ?>" oninput="calcTiPurchaseLine(this)">
                                <input type="number" name="tip_quantity[<?= $pi ?>][]" class="form-control" value="<?= $pd['quantity'] ?>" min="1" oninput="calcTiPurchaseLine(this)">
                                <input type="text" class="form-control input-money tip-subtotal" readonly value="<?= formatMoney($pd['subtotal']) ?>">
                                <button type="button" class="btn-remove" onclick="removeTiPurchLine(this)"><i class="fas fa-times"></i></button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addTiPurchaseLine(this, <?= $pi ?>)"><i class="fas fa-plus"></i> 제품추가</button>
                        <input type="hidden" name="tip_total[]" class="tip-total-input" value="<?= $p['total_amount'] ?>">
                        <input type="hidden" name="tip_vat[]" class="tip-vat-input" value="<?= $p['vat_amount'] ?>">
                        <div class="total-row" style="margin-top:8px;">
                            <span class="total-label">매입 소계</span>
                            <span class="tip-block-total" style="font-weight:700;color:var(--amber-glow);"><?= formatMoney($p['total_amount']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" id="ti-no-purchase-msg" style="padding:30px;">
                        <i class="fas fa-inbox" style="font-size:32px;"></i>
                        <h4 style="font-size:14px;">매입 정보가 없습니다</h4>
                        <p style="font-size:12px;">위의 "매입 추가" 버튼으로 추가하세요</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="padding:0 16px 16px;">
                <div style="border-top:2px solid var(--amber-glow);padding-top:12px;">
                    <div class="total-row">
                        <span class="total-label">매입 총액</span>
                        <span class="total-value" id="ti-purchase-total-display" style="color:var(--amber-glow);">0</span>
                    </div>
                    <input type="hidden" name="purchase_total_amount" id="ti_purchase_total_amount" value="<?= $invoice['purchase_total_amount'] ?? 0 ?>">
                    <div class="total-row" style="font-size:smaller;">
                        <span class="total-label">부가세 (10%)</span>
                        <span id="ti-purchase-vat-display" style="font-size:14px;color:var(--text-light);">0</span>
                    </div>
                    <input type="hidden" name="purchase_vat_amount" id="ti_purchase_vat_amount" value="<?= $invoice['purchase_vat_amount'] ?? 0 ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- ========== 처리 상태 + 영업이익 ========== -->
    <div class="profit-section mt-3">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:20px;text-align:center;">
            <div>
                <div class="profit-label"><i class="fas fa-chart-line"></i> 매출 총액</div>
                <div class="profit-value" id="ti-profit-sale">0</div>
            </div>
            <div>
                <div class="profit-label" style="color:var(--amber-glow);"><i class="fas fa-shopping-cart"></i> 매입 총액</div>
                <div class="profit-value" id="ti-profit-purchase" style="-webkit-text-fill-color:var(--amber-glow);color:var(--amber-glow);">0</div>
            </div>
            <div>
                <div class="profit-label"><i class="fas fa-coins"></i> 영업이익</div>
                <div class="profit-value" id="ti-profit-result">0</div>
            </div>
            <div>
                <label class="form-label" style="margin-bottom:4px;text-align:left;">처리 상태</label>
                <div class="d-flex gap-2" style="justify-content:center;">
                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;padding:6px 12px;border:1px solid rgba(16,185,129,0.3);border-radius:8px;font-weight:600;font-size:12px;color:var(--emerald);background:rgba(16,185,129,0.1);">
                        <input type="radio" name="status" value="requested" <?= ($invoice['status'] ?? 'requested') === 'requested' ? 'checked' : '' ?> onchange="toggleTiPendingReason()"> 요청
                    </label>
                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;padding:6px 12px;border:1px solid rgba(245,158,11,0.3);border-radius:8px;font-weight:600;font-size:12px;color:var(--amber-glow);background:rgba(245,158,11,0.1);">
                        <input type="radio" name="status" value="pending" <?= ($invoice['status'] ?? '') === 'pending' ? 'checked' : '' ?> onchange="toggleTiPendingReason()"> 보류
                    </label>
                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;padding:6px 12px;border:1px solid rgba(34,211,238,0.3);border-radius:8px;font-weight:600;font-size:12px;color:var(--cyan-accent);background:rgba(34,211,238,0.1);">
                        <input type="radio" name="status" value="completed" <?= ($invoice['status'] ?? '') === 'completed' ? 'checked' : '' ?> onchange="toggleTiPendingReason()"> 완료
                    </label>
                </div>
            </div>
        </div>
        <div class="form-group" id="ti-pendingReasonWrap" style="display:<?= ($invoice['status'] ?? '') === 'pending' ? 'block' : 'none' ?>;margin-top:12px;">
            <label class="form-label" style="color:var(--amber-glow);"><i class="fas fa-exclamation-triangle"></i> 보류 사유 <span class="text-danger">*</span></label>
            <textarea name="pending_reason" id="ti_pending_reason" class="form-control" rows="2" 
                placeholder="보류 사유를 입력하세요..." style="border-color:rgba(245,158,11,0.3);resize:vertical;"><?= e($invoice['pending_reason'] ?? '') ?></textarea>
        </div>
    </div>
    
    <!-- Submit -->
    <div class="d-flex justify-between mt-3">
        <a href="?page=taxinvoice" class="btn btn-outline"><i class="fas fa-arrow-left"></i> 목록으로</a>
        <button type="button" class="btn btn-primary btn-lg" onclick="submitTiForm()"><i class="fas fa-save"></i> <?= $isEdit ? '수정 저장' : '등록' ?></button>
    </div>
</form>

<?php
$vendorsJson = json_encode($vendors, JSON_UNESCAPED_UNICODE);
$saleItemsJson = json_encode($saleItems, JSON_UNESCAPED_UNICODE);
$pageScript = <<<JS
var tiPurchaseIdx = document.querySelectorAll('#ti-purchase-blocks .purchase-block').length;
var tiVendorsData = $vendorsJson;
var tiSaleItemsData = $saleItemsJson;

// === Flatpickr 초기화 ===
var tiSaleDateInput = document.getElementById('ti_sale_date');
var tiSaleDateVal = tiSaleDateInput ? tiSaleDateInput.value : '';
flatpickr('#ti_sale_date', { 
    locale: 'ko', dateFormat: 'Y-m-d', 
    defaultDate: tiSaleDateVal || 'today', allowInput: true,
    onChange: function(sel, dateStr) {
        var invIdField = document.querySelector('input[name="invoice_id"]');
        if (!invIdField) { updateTiSaleNumber(dateStr); }
    }
});

var tiDelivInput = document.getElementById('ti_delivery_date');
var tiDelivVal = tiDelivInput ? tiDelivInput.value : '';
flatpickr('#ti_delivery_date', {
    locale: 'ko', dateFormat: 'Y-m-d',
    defaultDate: tiDelivVal || null, allowInput: true
});

document.querySelectorAll('#ti-purchase-blocks .purchase-block .datepicker').forEach(function(el) {
    flatpickr(el, { locale: 'ko', dateFormat: 'Y-m-d', defaultDate: el.value || 'today', allowInput: true });
});

// === 매출번호 AJAX ===
function updateTiSaleNumber(dateStr) {
    if (!dateStr) return;
    fetch('?page=taxinvoice&action=generateNumber&date=' + encodeURIComponent(dateStr))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.sale_number) document.querySelector('input[name="sale_number"]').value = data.sale_number;
        }).catch(function(err) { console.error(err); });
}

// === 제품코드 옵션 ===
function buildTiSaleItemOptions(selectedId) {
    var opts = '<option value="">-- 선택 --</option>';
    tiSaleItemsData.forEach(function(si) {
        var sel = (selectedId && selectedId == si.id) ? ' selected' : '';
        opts += '<option value="'+si.id+'"'+sel+'>'+si.sort_order+'.'+si.name+'</option>';
    });
    return opts;
}

// === 매입업체 옵션 ===
function buildTiVendorOptions(selectedId) {
    var opts = '<option value="">-- 선택 --</option>';
    tiVendorsData.forEach(function(v) {
        var sel = (selectedId && selectedId == v.id) ? ' selected' : '';
        opts += '<option value="'+v.id+'"'+sel+'>'+v.name+'</option>';
    });
    return opts;
}

// === 판매 제품 라인 추가 ===
function addTiSaleLine() {
    var html = '<div class="sale-line-item">' +
        '<input type="text" name="ti_product_name[]" class="form-control" placeholder="제품명">' +
        '<select name="ti_sale_item_id[]" class="form-control">' + buildTiSaleItemOptions() + '</select>' +
        '<input type="text" name="ti_unit_price[]" class="form-control input-money" placeholder="단가" oninput="calcTiSaleLine(this)">' +
        '<input type="number" name="ti_quantity[]" class="form-control" value="1" min="1" oninput="calcTiSaleLine(this)">' +
        '<input type="text" class="form-control input-money ti-sale-subtotal" readonly value="0">' +
        '<button type="button" class="btn-remove" onclick="removeTiSaleLine(this)"><i class="fas fa-times"></i></button></div>';
    document.getElementById('ti-sale-lines').insertAdjacentHTML('beforeend', html);
    initMoneyInputs();
}

// === 판매 라인 계산 ===
function calcTiSaleLine(el) {
    var row = el.closest('.sale-line-item');
    var price = parseNumber(row.querySelector('[name="ti_unit_price[]"]').value);
    var qty = parseInt(row.querySelector('[name="ti_quantity[]"]').value) || 1;
    row.querySelector('.ti-sale-subtotal').value = formatNumber(price * qty);
    calcTiSaleTotal();
}

function calcTiSaleTotal() {
    var total = 0;
    document.querySelectorAll('.ti-sale-subtotal').forEach(function(el) { total += parseNumber(el.value); });
    document.getElementById('ti-sale-total-display').textContent = formatNumber(total);
    document.getElementById('ti_total_amount').value = total;
    var vat = Math.floor(total * 0.1);
    document.getElementById('ti-sale-vat-display').textContent = formatNumber(vat);
    document.getElementById('ti_vat_amount').value = vat;
    calcTiProfit();
}

function removeTiSaleLine(btn) {
    var container = btn.closest('.line-items');
    if (container.querySelectorAll('.sale-line-item').length > 1) {
        btn.closest('.sale-line-item').remove();
        calcTiSaleTotal();
    }
}

// === 매입 블록 추가 ===
function addTiPurchaseBlock() {
    var noMsg = document.getElementById('ti-no-purchase-msg');
    if (noMsg) noMsg.remove();
    var vendorOpts = buildTiVendorOptions();
    var saleDate = document.getElementById('ti_sale_date').value || new Date().toISOString().slice(0,10);
    var html = '<div class="purchase-block" style="border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:12px;margin-bottom:12px;background:rgba(245,158,11,0.04);">' +
        '<div class="d-flex justify-between align-center mb-1">' +
        '<strong style="color:var(--amber-glow);font-size:13px;">매입 #' + (tiPurchaseIdx+1) + '</strong>' +
        '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(&quot;.purchase-block&quot;).remove();calcTiPurchaseTotals();">삭제</button></div>' +
        '<div class="form-row"><div class="form-group"><label class="form-label">매입일자</label>' +
        '<input type="text" name="tip_date[]" class="form-control datepicker" value="' + saleDate + '"></div></div>' +
        '<div class="form-label" style="margin-top:8px;color:var(--amber-glow);font-weight:700;font-size:12px;"><i class="fas fa-box"></i> 매입 제품</div>' +
        '<div class="purch-line-header"><span>제품명</span><span>매입업체</span><span>단가</span><span>수량</span><span>소계</span><span></span></div>' +
        '<div class="purchase-lines line-items">' +
        '<div class="purch-line-item">' +
        '<input type="text" name="tip_product_name['+tiPurchaseIdx+'][]" class="form-control" placeholder="제품명">' +
        '<select name="tip_vendor_id['+tiPurchaseIdx+'][]" class="form-control">' + vendorOpts + '</select>' +
        '<input type="text" name="tip_unit_price['+tiPurchaseIdx+'][]" class="form-control input-money" placeholder="단가" oninput="calcTiPurchaseLine(this)">' +
        '<input type="number" name="tip_quantity['+tiPurchaseIdx+'][]" class="form-control" value="1" min="1" oninput="calcTiPurchaseLine(this)">' +
        '<input type="text" class="form-control input-money tip-subtotal" readonly value="0">' +
        '<button type="button" class="btn-remove" onclick="removeTiPurchLine(this)"><i class="fas fa-times"></i></button>' +
        '</div></div>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="addTiPurchaseLine(this,'+tiPurchaseIdx+')"><i class="fas fa-plus"></i> 제품추가</button>' +
        '<input type="hidden" name="tip_total[]" class="tip-total-input" value="0">' +
        '<input type="hidden" name="tip_vat[]" class="tip-vat-input" value="0">' +
        '<div class="total-row" style="margin-top:8px;"><span class="total-label">매입 소계</span><span class="tip-block-total" style="font-weight:700;color:var(--amber-glow);">0</span></div></div>';
    document.getElementById('ti-purchase-blocks').insertAdjacentHTML('beforeend', html);
    tiPurchaseIdx++;
    var newDatepickers = document.querySelectorAll('#ti-purchase-blocks .purchase-block:last-child .datepicker');
    newDatepickers.forEach(function(el) {
        flatpickr(el, { locale: 'ko', dateFormat: 'Y-m-d', defaultDate: saleDate, allowInput: true });
    });
    initMoneyInputs();
}

// === 매입 라인 추가 ===
function addTiPurchaseLine(btn, idx) {
    var container = btn.previousElementSibling;
    while (!container.classList.contains('line-items')) container = container.previousElementSibling;
    var vendorOpts = buildTiVendorOptions();
    var html = '<div class="purch-line-item">' +
        '<input type="text" name="tip_product_name['+idx+'][]" class="form-control" placeholder="제품명">' +
        '<select name="tip_vendor_id['+idx+'][]" class="form-control">' + vendorOpts + '</select>' +
        '<input type="text" name="tip_unit_price['+idx+'][]" class="form-control input-money" placeholder="단가" oninput="calcTiPurchaseLine(this)">' +
        '<input type="number" name="tip_quantity['+idx+'][]" class="form-control" value="1" min="1" oninput="calcTiPurchaseLine(this)">' +
        '<input type="text" class="form-control input-money tip-subtotal" readonly value="0">' +
        '<button type="button" class="btn-remove" onclick="removeTiPurchLine(this)"><i class="fas fa-times"></i></button></div>';
    container.insertAdjacentHTML('beforeend', html);
    initMoneyInputs();
}

function removeTiPurchLine(btn) {
    var container = btn.closest('.line-items');
    if (container.querySelectorAll('.purch-line-item').length > 1) {
        btn.closest('.purch-line-item').remove();
        calcTiPurchaseTotals();
    }
}

function calcTiPurchaseLine(el) {
    var row = el.closest('.purch-line-item');
    var inputs = row.querySelectorAll('.input-money');
    var price = parseNumber(inputs[0].value);
    var qty = parseInt(row.querySelector('[type="number"]').value) || 1;
    row.querySelector('.tip-subtotal').value = formatNumber(price * qty);
    calcTiPurchaseTotals();
}

function calcTiPurchaseTotals() {
    var grandTotal = 0;
    document.querySelectorAll('#ti-purchase-blocks .purchase-block').forEach(function(block) {
        var blockTotal = 0;
        block.querySelectorAll('.tip-subtotal').forEach(function(el) { blockTotal += parseNumber(el.value); });
        block.querySelector('.tip-total-input').value = blockTotal;
        block.querySelector('.tip-vat-input').value = Math.floor(blockTotal * 0.1);
        block.querySelector('.tip-block-total').textContent = formatNumber(blockTotal);
        grandTotal += blockTotal;
    });
    document.getElementById('ti-purchase-total-display').textContent = formatNumber(grandTotal);
    document.getElementById('ti_purchase_total_amount').value = grandTotal;
    document.getElementById('ti-purchase-vat-display').textContent = formatNumber(Math.floor(grandTotal * 0.1));
    document.getElementById('ti_purchase_vat_amount').value = Math.floor(grandTotal * 0.1);
    calcTiProfit();
}

// === 영업이익 ===
function calcTiProfit() {
    var s = parseNumber(document.getElementById('ti-sale-total-display').textContent);
    var p = parseNumber(document.getElementById('ti-purchase-total-display').textContent);
    var profit = s - p;
    document.getElementById('ti-profit-sale').textContent = formatNumber(s);
    document.getElementById('ti-profit-purchase').textContent = formatNumber(p);
    document.getElementById('ti-profit-result').textContent = formatNumber(profit);
    document.getElementById('ti-profit-result').style.color = profit >= 0 ? 'var(--success)' : 'var(--danger)';
}

// === 보류 사유 토글 ===
function toggleTiPendingReason() {
    var st = document.querySelector('input[name="status"]:checked');
    var wrap = document.getElementById('ti-pendingReasonWrap');
    var ta = document.getElementById('ti_pending_reason');
    if (st && st.value === 'pending') { wrap.style.display = 'block'; ta.required = true; }
    else { wrap.style.display = 'none'; ta.required = false; }
}

// === 폼 제출 ===
function submitTiForm() {
    var form = document.getElementById('tiForm');
    var st = document.querySelector('input[name="status"]:checked');
    if (st && st.value === 'completed') {
        if (!confirm('완료로 처리하면 매출/매입 관리에 자동 등록됩니다.\\n완료 후에는 수정할 수 없습니다.\\n계속 진행하시겠습니까?')) return;
    }
    form.submit();
}

// Init
calcTiSaleTotal();
calcTiPurchaseTotals();
JS;
?>
