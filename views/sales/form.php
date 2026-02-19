<form method="POST" action="?page=sales&action=save" id="saleForm">
    <?= CSRF::field() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
    <?php endif; ?>
    
    <!-- Two Column Layout: Sales (Left) + Purchase (Right) -->
    <div class="sale-form-grid">
        
        <!-- ========== LEFT: Sales Section ========== -->
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
                        <input type="text" name="sale_date" id="sale_date" class="form-control datepicker" 
                               value="<?= e($sale['sale_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">출고일자</label>
                        <input type="text" name="delivery_date" id="delivery_date" class="form-control datepicker" 
                               value="<?= e($sale['delivery_date'] ?? '') ?>" placeholder="출고일자 선택">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">업체명</label>
                        <select name="company_id" id="company_id" class="form-control">
                            <option value="">-- 업체 선택 --</option>
                            <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($sale['company_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Sale Line Items -->
                <div class="form-label" style="margin-top:12px;">판매 제품</div>
                <div class="sale-line-header">
                    <span>제품명</span><span>제품코드</span><span>단가</span><span>수량</span><span>소계</span><span></span>
                </div>
                <div id="sale-lines" class="line-items">
                    <?php if (!empty($saleDetails)): ?>
                        <?php foreach ($saleDetails as $idx => $d): ?>
                        <div class="sale-line-item">
                            <input type="text" name="product_name[]" class="form-control" placeholder="제품명" value="<?= e($d['product_name']) ?>">
                            <select name="sale_item_id[]" class="form-control">
                                <option value="">-- 선택 --</option>
                                <?php foreach ($saleItems as $si): ?>
                                <option value="<?= $si['id'] ?>" <?= ($d['sale_item_id'] ?? '') == $si['id'] ? 'selected' : '' ?>><?= e($si['sort_order'] . '.' . $si['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="unit_price[]" class="form-control input-money" placeholder="단가" value="<?= formatMoney($d['unit_price']) ?>" oninput="calcSaleLine(this)">
                            <input type="number" name="quantity[]" class="form-control" value="<?= $d['quantity'] ?>" min="1" oninput="calcSaleLine(this)">
                            <input type="text" class="form-control input-money sale-subtotal" readonly value="<?= formatMoney($d['subtotal']) ?>">
                            <button type="button" class="btn-remove" onclick="removeSaleLine(this)"><i class="fas fa-times"></i></button>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="sale-line-item">
                            <input type="text" name="product_name[]" class="form-control" placeholder="제품명">
                            <select name="sale_item_id[]" class="form-control">
                                <option value="">-- 선택 --</option>
                                <?php foreach ($saleItems as $si): ?>
                                <option value="<?= $si['id'] ?>"><?= e($si['sort_order'] . '.' . $si['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="unit_price[]" class="form-control input-money" placeholder="단가" oninput="calcSaleLine(this)">
                            <input type="number" name="quantity[]" class="form-control" value="1" min="1" oninput="calcSaleLine(this)">
                            <input type="text" class="form-control input-money sale-subtotal" readonly value="0">
                            <button type="button" class="btn-remove" onclick="removeSaleLine(this)"><i class="fas fa-times"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="addSaleLine()"><i class="fas fa-plus"></i> 제품 추가</button>
                
                <div style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.06);padding-top:12px;">
                    <div class="total-row">
                        <span class="total-label">매출 총액</span>
                        <span class="total-value" id="sale-total-display">0</span>
                    </div>
                    <input type="hidden" name="sale_total" id="sale_total" value="<?= $sale['total_amount'] ?? 0 ?>">
                    <div class="total-row" style="font-size:smaller;">
                        <span class="total-label">부가세 (10%)</span>
                        <span class="total-value" id="sale-vat-display" style="font-size:14px;color:var(--text-light);">0</span>
                    </div>
                    <input type="hidden" name="sale_vat" id="sale_vat" value="<?= $sale['vat_amount'] ?? 0 ?>">
                </div>
            </div>
        </div>
        
        <!-- ========== RIGHT: Purchase Section ========== -->
        <div class="sale-section">
            <div class="sale-section-header purchase-header">
                <i class="fas fa-shopping-cart"></i> 매입 정보
                <button type="button" class="btn btn-outline btn-sm" style="margin-left:auto;" onclick="addPurchaseBlock()"><i class="fas fa-plus"></i> 매입 추가</button>
            </div>
            <div class="sale-section-body" id="purchase-blocks">
                <?php if (!empty($purchases)): ?>
                    <?php foreach ($purchases as $pi => $p): ?>
                    <div class="purchase-block" style="border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:12px;margin-bottom:12px;background:rgba(245,158,11,0.04);">
                        <div class="d-flex justify-between align-center mb-1">
                            <strong style="color:var(--amber-glow);font-size:13px;">매입 #<?= $pi + 1 ?></strong>
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.purchase-block').remove();calcPurchaseTotals();">삭제</button>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">매입일자</label>
                                <input type="text" name="p_date[]" class="form-control datepicker" value="<?= e($p['purchase_date']) ?>">
                            </div>
                        </div>
                        <div class="form-label" style="margin-top:8px;color:var(--amber-glow);font-weight:700;font-size:12px;"><i class="fas fa-box"></i> 매입 제품</div>
                        <div class="purch-line-header"><span>제품명</span><span>매입업체</span><span>단가</span><span>수량</span><span>소계</span><span></span></div>
                        <div class="purchase-lines line-items">
                            <?php foreach ($p['details'] as $pd): ?>
                            <div class="purch-line-item">
                                <input type="text" name="p_product_name[<?= $pi ?>][]" class="form-control" value="<?= e($pd['product_name']) ?>">
                                <select name="p_vendor_id[<?= $pi ?>][]" class="form-control">
                                    <option value="">-- 선택 --</option>
                                    <?php foreach ($vendors as $v): ?>
                                    <option value="<?= $v['id'] ?>" <?= ($pd['vendor_id'] ?? $p['vendor_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="p_unit_price[<?= $pi ?>][]" class="form-control input-money" value="<?= formatMoney($pd['unit_price']) ?>" oninput="calcPurchaseLine(this)">
                                <input type="number" name="p_quantity[<?= $pi ?>][]" class="form-control" value="<?= $pd['quantity'] ?>" min="1" oninput="calcPurchaseLine(this)">
                                <input type="text" class="form-control input-money p-subtotal" readonly value="<?= formatMoney($pd['subtotal']) ?>">
                                <button type="button" class="btn-remove" onclick="removePurchLine(this)"><i class="fas fa-times"></i></button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addPurchaseLine(this, <?= $pi ?>)"><i class="fas fa-plus"></i> 제품추가</button>
                        <input type="hidden" name="p_total[]" class="p-total-input" value="<?= $p['total_amount'] ?>">
                        <input type="hidden" name="p_vat[]" class="p-vat-input" value="<?= $p['vat_amount'] ?>">
                        <div class="total-row" style="margin-top:8px;">
                            <span class="total-label">매입 소계</span>
                            <span class="p-block-total" style="font-weight:700;color:var(--amber-glow);"><?= formatMoney($p['total_amount']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" id="no-purchase-msg" style="padding:30px;">
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
                        <span class="total-value" id="purchase-total-display" style="color:var(--amber-glow);">0</span>
                    </div>
                    <div class="total-row" style="font-size:smaller;">
                        <span class="total-label">부가세 (10%)</span>
                        <span id="purchase-vat-display" style="font-size:14px;color:var(--text-light);">0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ========== Profit Section ========== -->
    <div class="profit-section mt-3">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;text-align:center;">
            <div>
                <div class="profit-label"><i class="fas fa-chart-line"></i> 매출 총액</div>
                <div class="profit-value" id="profit-sale">0</div>
            </div>
            <div>
                <div class="profit-label" style="color:var(--amber-glow);"><i class="fas fa-shopping-cart"></i> 매입 총액</div>
                <div class="profit-value" id="profit-purchase" style="-webkit-text-fill-color:var(--amber-glow);color:var(--amber-glow);">0</div>
            </div>
            <div>
                <div class="profit-label"><i class="fas fa-coins"></i> 영업이익</div>
                <div class="profit-value" id="profit-result">0</div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">부가세(10%): <span id="profit-vat">0</span></div>
            </div>
        </div>
    </div>
    
    <!-- Submit -->
    <div class="d-flex justify-between mt-3">
        <a href="?page=sales" class="btn btn-outline"><i class="fas fa-arrow-left"></i> 목록으로</a>
        <button type="button" class="btn btn-primary btn-lg" onclick="submitSaleForm()"><i class="fas fa-save"></i> <?= $isEdit ? '수정 저장' : '등록' ?></button>
    </div>
</form>

<?php
$vendorsJson = json_encode($vendors, JSON_UNESCAPED_UNICODE);
$saleItemsJson = json_encode($saleItems, JSON_UNESCAPED_UNICODE);
$pageScript = <<<JS
var purchaseIdx = document.querySelectorAll('.purchase-block').length;
var vendorsData = $vendorsJson;
var saleItemsData = $saleItemsJson;

// === Flatpickr 초기화 ===
var saleDateInput = document.getElementById('sale_date');
var saleDateVal = saleDateInput ? saleDateInput.value : '';
flatpickr('#sale_date', { 
    locale: 'ko', 
    dateFormat: 'Y-m-d', 
    defaultDate: saleDateVal || 'today',
    allowInput: true,
    onChange: function(selectedDates, dateStr) {
        var saleIdField = document.querySelector('input[name="sale_id"]');
        if (!saleIdField) {
            updateSaleNumber(dateStr);
        }
    }
});

// 출고일자 Flatpickr 초기화
var deliveryDateInput = document.getElementById('delivery_date');
var deliveryDateVal = deliveryDateInput ? deliveryDateInput.value : '';
flatpickr('#delivery_date', {
    locale: 'ko',
    dateFormat: 'Y-m-d',
    defaultDate: deliveryDateVal || null,
    allowInput: true
});

document.querySelectorAll('.purchase-block .datepicker').forEach(function(el) {
    var existingVal = el.value;
    flatpickr(el, { 
        locale: 'ko', 
        dateFormat: 'Y-m-d', 
        defaultDate: existingVal || 'today',
        allowInput: true
    });
});

// === 매출번호 자동갱신 AJAX ===
function updateSaleNumber(dateStr) {
    if (!dateStr) return;
    fetch('?page=sales&action=generateNumber&date=' + encodeURIComponent(dateStr))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.sale_number) {
                document.querySelector('input[name="sale_number"]').value = data.sale_number;
            }
        })
        .catch(function(err) { console.error('매출번호 갱신 실패:', err); });
}

// === 제품코드 옵션 HTML 생성 ===
function buildSaleItemOptions(selectedId) {
    var opts = '<option value="">-- 선택 --</option>';
    saleItemsData.forEach(function(si) {
        var sel = (selectedId && selectedId == si.id) ? ' selected' : '';
        opts += '<option value="'+si.id+'"'+sel+'>'+si.sort_order+'.'+si.name+'</option>';
    });
    return opts;
}

// === 매입업체 옵션 HTML 생성 ===
function buildVendorOptions(selectedId) {
    var opts = '<option value="">-- 선택 --</option>';
    vendorsData.forEach(function(v) {
        var sel = (selectedId && selectedId == v.id) ? ' selected' : '';
        opts += '<option value="'+v.id+'"'+sel+'>'+v.name+'</option>';
    });
    return opts;
}

// === 매출 라인 추가 ===
function addSaleLine() {
    var html = '<div class="sale-line-item">' +
        '<input type="text" name="product_name[]" class="form-control" placeholder="제품명">' +
        '<select name="sale_item_id[]" class="form-control">' + buildSaleItemOptions() + '</select>' +
        '<input type="text" name="unit_price[]" class="form-control input-money" placeholder="단가" oninput="calcSaleLine(this)">' +
        '<input type="number" name="quantity[]" class="form-control" value="1" min="1" oninput="calcSaleLine(this)">' +
        '<input type="text" class="form-control input-money sale-subtotal" readonly value="0">' +
        '<button type="button" class="btn-remove" onclick="removeSaleLine(this)"><i class="fas fa-times"></i></button>' +
        '</div>';
    document.getElementById('sale-lines').insertAdjacentHTML('beforeend', html);
    initMoneyInputs();
}

// === 매출 라인 계산 ===
function calcSaleLine(el) {
    var row = el.closest('.sale-line-item');
    var price = parseNumber(row.querySelector('[name="unit_price[]"]').value);
    var qty = parseInt(row.querySelector('[name="quantity[]"]').value) || 1;
    var subtotal = price * qty;
    row.querySelector('.sale-subtotal').value = formatNumber(subtotal);
    calcSaleTotal();
}

// === 매출 합계 ===
function calcSaleTotal() {
    var total = 0;
    document.querySelectorAll('.sale-subtotal').forEach(function(el) {
        total += parseNumber(el.value);
    });
    document.getElementById('sale-total-display').textContent = formatNumber(total);
    document.getElementById('sale_total').value = total;
    var vat = Math.floor(total * 0.1);
    document.getElementById('sale-vat-display').textContent = formatNumber(vat);
    document.getElementById('sale_vat').value = vat;
    calcProfit();
}

// === 매출 라인 삭제 ===
function removeSaleLine(btn) {
    var container = btn.closest('.line-items');
    if (container.querySelectorAll('.sale-line-item').length > 1) {
        btn.closest('.sale-line-item').remove();
        calcSaleTotal();
    }
}

// === 매입 블록 추가 ===
function addPurchaseBlock() {
    var noMsg = document.getElementById('no-purchase-msg');
    if (noMsg) noMsg.remove();
    
    var vendorOpts = buildVendorOptions();
    
    var html = '<div class="purchase-block" style="border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:12px;margin-bottom:12px;background:rgba(245,158,11,0.04);">' +
        '<div class="d-flex justify-between align-center mb-1">' +
        '<strong style="color:var(--amber-glow);font-size:13px;">매입 #' + (purchaseIdx+1) + '</strong>' +
        '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\\'.purchase-block\\').remove();calcPurchaseTotals();">삭제</button></div>' +
        '<div class="form-row">' +
        '<div class="form-group"><label class="form-label">매입일자</label><input type="text" name="p_date[]" class="form-control datepicker" value="' + (document.getElementById('sale_date').value || new Date().toISOString().slice(0,10)) + '"></div>' +
        '</div>' +
        '<div class="form-label" style="margin-top:8px;color:var(--amber-glow);font-weight:700;font-size:12px;"><i class="fas fa-box"></i> 매입 제품</div>' +
        '<div class="purch-line-header"><span>제품명</span><span>매입업체</span><span>단가</span><span>수량</span><span>소계</span><span></span></div>' +
        '<div class="purchase-lines line-items">' +
        '<div class="purch-line-item">' +
        '<input type="text" name="p_product_name['+purchaseIdx+'][]" class="form-control" placeholder="제품명">' +
        '<select name="p_vendor_id['+purchaseIdx+'][]" class="form-control">' + vendorOpts + '</select>' +
        '<input type="text" name="p_unit_price['+purchaseIdx+'][]" class="form-control input-money" placeholder="단가" oninput="calcPurchaseLine(this)">' +
        '<input type="number" name="p_quantity['+purchaseIdx+'][]" class="form-control" value="1" min="1" oninput="calcPurchaseLine(this)">' +
        '<input type="text" class="form-control input-money p-subtotal" readonly value="0">' +
        '<button type="button" class="btn-remove" onclick="removePurchLine(this)"><i class="fas fa-times"></i></button>' +
        '</div></div>' +
        '<button type="button" class="btn btn-outline btn-sm" onclick="addPurchaseLine(this,'+purchaseIdx+')"><i class="fas fa-plus"></i> 제품추가</button>' +
        '<input type="hidden" name="p_total[]" class="p-total-input" value="0">' +
        '<input type="hidden" name="p_vat[]" class="p-vat-input" value="0">' +
        '<div class="total-row" style="margin-top:8px;"><span class="total-label">매입 소계</span><span class="p-block-total" style="font-weight:700;color:var(--amber-glow);">0</span></div>' +
        '</div>';
    
    document.getElementById('purchase-blocks').insertAdjacentHTML('beforeend', html);
    purchaseIdx++;
    var currentSaleDate = document.getElementById('sale_date').value || 'today';
    var newDatepickers = document.querySelectorAll('.purchase-block:last-child .datepicker');
    newDatepickers.forEach(function(el) {
        flatpickr(el, { locale: 'ko', dateFormat: 'Y-m-d', defaultDate: currentSaleDate, allowInput: true });
    });
    initMoneyInputs();
}

// === 매입 라인 추가 ===
function addPurchaseLine(btn, idx) {
    var container = btn.previousElementSibling;
    while (!container.classList.contains('line-items')) container = container.previousElementSibling;
    var vendorOpts = buildVendorOptions();
    var html = '<div class="purch-line-item">' +
        '<input type="text" name="p_product_name['+idx+'][]" class="form-control" placeholder="제품명">' +
        '<select name="p_vendor_id['+idx+'][]" class="form-control">' + vendorOpts + '</select>' +
        '<input type="text" name="p_unit_price['+idx+'][]" class="form-control input-money" placeholder="단가" oninput="calcPurchaseLine(this)">' +
        '<input type="number" name="p_quantity['+idx+'][]" class="form-control" value="1" min="1" oninput="calcPurchaseLine(this)">' +
        '<input type="text" class="form-control input-money p-subtotal" readonly value="0">' +
        '<button type="button" class="btn-remove" onclick="removePurchLine(this)"><i class="fas fa-times"></i></button></div>';
    container.insertAdjacentHTML('beforeend', html);
    initMoneyInputs();
}

// === 매입 라인 삭제 ===
function removePurchLine(btn) {
    var container = btn.closest('.line-items');
    if (container.querySelectorAll('.purch-line-item').length > 1) {
        btn.closest('.purch-line-item').remove();
        calcPurchaseTotals();
    }
}

// === 매입 라인 계산 ===
function calcPurchaseLine(el) {
    var row = el.closest('.purch-line-item');
    var inputs = row.querySelectorAll('.input-money');
    var price = parseNumber(inputs[0].value);
    var qty = parseInt(row.querySelector('[type="number"]').value) || 1;
    row.querySelector('.p-subtotal').value = formatNumber(price * qty);
    calcPurchaseTotals();
}

// === 매입 전체 합계 ===
function calcPurchaseTotals() {
    var grandTotal = 0;
    document.querySelectorAll('.purchase-block').forEach(function(block) {
        var blockTotal = 0;
        block.querySelectorAll('.p-subtotal').forEach(function(el) { blockTotal += parseNumber(el.value); });
        block.querySelector('.p-total-input').value = blockTotal;
        block.querySelector('.p-vat-input').value = Math.floor(blockTotal * 0.1);
        block.querySelector('.p-block-total').textContent = formatNumber(blockTotal);
        grandTotal += blockTotal;
    });
    document.getElementById('purchase-total-display').textContent = formatNumber(grandTotal);
    document.getElementById('purchase-vat-display').textContent = formatNumber(Math.floor(grandTotal * 0.1));
    calcProfit();
}

// === 영업이익 계산 ===
function calcProfit() {
    var saleTotal = parseNumber(document.getElementById('sale-total-display').textContent);
    var purchaseTotal = parseNumber(document.getElementById('purchase-total-display').textContent);
    var profit = saleTotal - purchaseTotal;
    
    document.getElementById('profit-sale').textContent = formatNumber(saleTotal);
    document.getElementById('profit-purchase').textContent = formatNumber(purchaseTotal);
    document.getElementById('profit-result').textContent = formatNumber(profit);
    document.getElementById('profit-result').style.color = profit >= 0 ? 'var(--success)' : 'var(--danger)';
    document.getElementById('profit-vat').textContent = formatNumber(Math.floor(Math.abs(profit) * 0.1));
}

// Init calculations on load
calcSaleTotal();
calcPurchaseTotals();

// === 업체명 미선택 확인 팝업 + 폼 제출 ===
function submitSaleForm() {
    var form = document.getElementById('saleForm');
    var companySelect = document.getElementById('company_id');
    var companyVal = companySelect ? companySelect.value : '';
    
    // 매출 제품명 필수 검증 (매출 정보가 있는 경우만)
    var saleTotal = parseNumber(document.getElementById('sale-total-display').textContent);
    if (saleTotal > 0) {
        var productNames = document.querySelectorAll('#sale-lines input[name="product_name[]"]');
        var hasProduct = false;
        productNames.forEach(function(el) { if (el.value.trim()) hasProduct = true; });
        if (!hasProduct) {
            alert('매출 제품명을 입력해주세요.');
            return;
        }
    }
    
    if (!companyVal) {
        if (confirm('업체명이 선택되지 않았습니다.\n계속 진행하시겠습니까?')) {
            form.submit();
        }
    } else {
        form.submit();
    }
}
JS;
?>
