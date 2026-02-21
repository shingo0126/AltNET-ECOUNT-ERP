<?php
$statusLabels = ['requested' => '요청', 'pending' => '보류', 'completed' => '완료'];
$statusColors = ['requested' => '#22d3ee', 'pending' => '#f59e0b', 'completed' => '#3b82f6'];
$statusBg     = ['requested' => 'rgba(34,211,238,0.12)', 'pending' => 'rgba(245,158,11,0.12)', 'completed' => 'rgba(59,130,246,0.12)'];
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
    <h2 style="font-size:20px;font-weight:700;color:var(--text-primary);margin:0;">
        <i class="fas fa-file-invoice" style="color:var(--cyan-accent);"></i> 세금계산서 발행 요청 관리
    </h2>
    <a href="?page=taxinvoice&action=create" class="btn btn-primary">
        <i class="fas fa-plus"></i> 발행 요청 등록
    </a>
</div>

<!-- 집계 카드 -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
    <div class="stat-card success">
        <div class="stat-label"><i class="fas fa-paper-plane" style="color:var(--emerald);"></i> 발행 요청</div>
        <div class="stat-value"><?= number_format($reqOnlyCount) ?></div>
        <div class="stat-sub">건</div>
    </div>
    <div class="stat-card warning">
        <div class="stat-label"><i class="fas fa-pause-circle" style="color:var(--amber-glow);"></i> 보류</div>
        <div class="stat-value"><?= number_format($pendingCount) ?></div>
        <div class="stat-sub">건</div>
    </div>
    <div class="stat-card accent">
        <div class="stat-label"><i class="fas fa-check-circle" style="color:var(--cyan-accent);"></i> 발행 완료</div>
        <div class="stat-value"><?= number_format($compCount) ?></div>
        <div class="stat-sub">건</div>
    </div>
</div>

<!-- ===== 발행 요청 리스트 ===== -->
<div class="card mb-2">
    <div class="card-header">
        <h3><i class="fas fa-list-alt" style="color:var(--emerald);"></i> 발행 요청 건 (요청/보류)</h3>
        <a href="?page=taxinvoice&action=exportRequested" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV 다운로드</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>매출번호</th>
                        <th>매출일자</th>
                        <th>출고일자</th>
                        <th>업체명</th>
                        <th class="text-right">매출총액</th>
                        <th class="text-right">매입총액</th>
                        <th style="width:80px;text-align:center;">처리상태</th>
                        <th style="width:160px;">보류 사유</th>
                        <th style="width:120px;text-align:center;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requestList)): ?>
                    <tr><td colspan="10" class="text-center" style="padding:30px;color:var(--text-muted);">발행 요청 건이 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($requestList as $idx => $r): ?>
                    <tr>
                        <td><?= $reqPag['offset'] + $idx + 1 ?></td>
                        <td style="white-space:nowrap;font-weight:600;color:var(--cyan-accent);"><?= e($r['sale_number'] ?: '-') ?></td>
                        <td style="white-space:nowrap;"><?= e($r['request_date']) ?></td>
                        <td style="white-space:nowrap;"><?= e($r['delivery_date'] ?: '-') ?></td>
                        <td><strong><?= e($r['company_name']) ?></strong></td>
                        <td class="money"><?= formatMoney($r['total_amount']) ?>원</td>
                        <td class="money" style="color:var(--amber-glow);"><?= formatMoney($r['purchase_total_amount']) ?>원</td>
                        <td style="text-align:center;">
                            <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;
                                color:<?= $statusColors[$r['status']] ?>;background:<?= $statusBg[$r['status']] ?>;">
                                <?= $statusLabels[$r['status']] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'pending' && !empty($r['pending_reason'])): ?>
                            <div style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--amber-glow);cursor:help;"
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
                            <a href="?page=taxinvoice&action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm" title="편집"><i class="fas fa-edit"></i></a>
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
        <h3><i class="fas fa-check-double" style="color:var(--cyan-accent);"></i> 발행 완료 건</h3>
        <a href="?page=taxinvoice&action=exportCompleted" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV 다운로드</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>매출번호</th>
                        <th>매출일자</th>
                        <th>출고일자</th>
                        <th>업체명</th>
                        <th class="text-right">매출총액</th>
                        <th class="text-right">매입총액</th>
                        <th style="width:80px;text-align:center;">처리상태</th>
                        <th style="width:120px;text-align:center;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($completedList)): ?>
                    <tr><td colspan="9" class="text-center" style="padding:30px;color:var(--text-muted);">발행 완료 건이 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($completedList as $idx => $r): ?>
                    <tr>
                        <td><?= $compPag['offset'] + $idx + 1 ?></td>
                        <td style="white-space:nowrap;font-weight:600;color:var(--cyan-accent);"><?= e($r['sale_number'] ?: '-') ?></td>
                        <td style="white-space:nowrap;"><?= e($r['request_date']) ?></td>
                        <td style="white-space:nowrap;"><?= e($r['delivery_date'] ?: '-') ?></td>
                        <td><strong><?= e($r['company_name']) ?></strong></td>
                        <td class="money"><?= formatMoney($r['total_amount']) ?>원</td>
                        <td class="money" style="color:var(--amber-glow);"><?= formatMoney($r['purchase_total_amount']) ?>원</td>
                        <td style="text-align:center;">
                            <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;
                                color:#3b82f6;background:rgba(59,130,246,0.12);">완료</span>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <button class="btn btn-outline btn-sm" onclick="openViewPopup(<?= $r['id'] ?>)" title="보기"><i class="fas fa-eye"></i></button>
                            <?php if ($isAdmin): ?>
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

<!-- ===== 보기 전용 팝업 ===== -->
<style>
#tiPopupContent::-webkit-scrollbar { width: 4px; }
#tiPopupContent::-webkit-scrollbar-track { background: transparent; }
#tiPopupContent::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }
#tiPopupContent::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }
#tiPopupContent { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.15) transparent; }
</style>
<div id="tiPopupOverlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:9998;background:rgba(0,0,0,0.5);"></div>
<div id="tiPopup" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
    width:auto;min-width:600px;max-width:95vw;z-index:9999;background:var(--card-bg);border-radius:16px;border:1px solid rgba(255,255,255,0.08);box-shadow:0 20px 60px rgba(0,0,0,0.4);">
    
    <div style="padding:12px 20px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;justify-content:space-between;align-items:center;">
        <h3 id="tiPopupTitle" style="margin:0;font-size:15px;font-weight:700;color:var(--text-primary);">
            <i class="fas fa-eye" style="color:var(--cyan-accent);"></i> 세금계산서 상세보기
        </h3>
        <button type="button" onclick="closeViewPopup()" class="modal-close"><i class="fas fa-times"></i></button>
    </div>
    
    <div id="tiPopupContent" style="padding:14px 20px;max-height:calc(90vh - 100px);overflow-y:auto;">
        <div style="text-align:center;padding:40px;color:var(--text-muted);">로딩 중...</div>
    </div>
    
    <div style="padding:10px 20px;border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeViewPopup()"><i class="fas fa-times"></i> 닫기</button>
    </div>
</div>

<?php
$pageScript = <<<JS

// ===== 보기 팝업 =====
function openViewPopup(id) {
    document.getElementById('tiPopupOverlay').style.display = 'block';
    document.getElementById('tiPopup').style.display = 'block';
    document.body.style.overflow = 'hidden';
    document.getElementById('tiPopupContent').innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> 로딩 중...</div>';
    
    fetch('?page=taxinvoice&action=getDetail&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { document.getElementById('tiPopupContent').innerHTML = '<div class="alert alert-danger">' + data.error + '</div>'; return; }
            renderViewContent(data);
        })
        .catch(function(err) { document.getElementById('tiPopupContent').innerHTML = '<div class="alert alert-danger">로드 실패: ' + err + '</div>'; });
}

function renderViewContent(data) {
    var inv = data.invoice;
    var det = data.details;
    var purch = data.purchases || [];
    
    var statusLabels = {requested:'요청', pending:'보류', completed:'완료'};
    var statusColors = {requested:'#22d3ee', pending:'#f59e0b', completed:'#3b82f6'};
    var statusBg = {requested:'rgba(34,211,238,0.12)', pending:'rgba(245,158,11,0.12)', completed:'rgba(59,130,246,0.12)'};
    
    var fmtM = function(v) { return new Intl.NumberFormat('ko-KR').format(parseInt(v) || 0); };
    
    var html = '<div style="display:flex;flex-wrap:wrap;gap:10px 24px;margin-bottom:14px;padding:10px 12px;border-radius:8px;background:rgba(255,255,255,0.02);">';
    html += '<div><span style="color:var(--text-muted);font-size:11px;">매출번호</span><div style="font-weight:600;color:var(--cyan-accent);font-size:13px;">' + (inv.sale_number || '-') + '</div></div>';
    html += '<div><span style="color:var(--text-muted);font-size:11px;">매출일자</span><div style="font-size:13px;">' + (inv.request_date || '-') + '</div></div>';
    html += '<div><span style="color:var(--text-muted);font-size:11px;">출고일자</span><div style="font-size:13px;">' + (inv.delivery_date || '-') + '</div></div>';
    html += '<div><span style="color:var(--text-muted);font-size:11px;">업체명</span><div style="font-size:13px;"><strong>' + (inv.company_name || '미지정') + '</strong></div></div>';
    html += '<div><span style="color:var(--text-muted);font-size:11px;">상태</span><div><span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;color:' + (statusColors[inv.status]||'#fff') + ';background:' + (statusBg[inv.status]||'transparent') + ';">' + (statusLabels[inv.status]||inv.status) + '</span></div></div>';
    if (inv.status === 'pending' && inv.pending_reason) {
        html += '<div><span style="color:var(--amber-glow);font-size:11px;">보류 사유</span><div style="color:var(--amber-glow);font-size:13px;">' + inv.pending_reason + '</div></div>';
    }
    html += '</div>';
    
    // 판매 제품
    html += '<div style="margin-bottom:12px;"><div style="font-weight:700;margin-bottom:6px;font-size:13px;color:var(--text-primary);"><i class="fas fa-chart-line" style="color:var(--cyan-accent);"></i> 판매 제품</div>';
    html += '<table class="data-table" style="font-size:12px;"><thead><tr><th>제품명</th><th>제품코드</th><th class="text-right">단가</th><th class="text-right" style="width:40px;">수량</th><th class="text-right">소계</th></tr></thead><tbody>';
    if (det.length === 0) {
        html += '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);">제품 없음</td></tr>';
    } else {
        det.forEach(function(d) {
            var itemCode = d.item_sort ? d.item_sort + '.' + d.item_name : '-';
            html += '<tr><td>' + d.product_name + '</td><td>' + itemCode + '</td><td class="money">' + fmtM(d.unit_price) + '</td><td class="text-right">' + d.quantity + '</td><td class="money">' + fmtM(d.subtotal) + '</td></tr>';
        });
    }
    html += '</tbody></table>';
    html += '<div style="text-align:right;margin-top:6px;font-weight:700;font-size:13px;">매출 총액: <span style="color:var(--cyan-accent);">' + fmtM(inv.total_amount) + '원</span> / 부가세: ' + fmtM(inv.vat_amount) + '원</div></div>';
    
    // 매입 정보
    if (purch.length > 0) {
        html += '<div><div style="font-weight:700;margin-bottom:6px;font-size:13px;color:var(--text-primary);"><i class="fas fa-shopping-cart" style="color:var(--amber-glow);"></i> 매입 정보</div>';
        purch.forEach(function(p, pi) {
            html += '<div style="border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:8px;margin-bottom:6px;background:rgba(245,158,11,0.04);">';
            html += '<div style="font-weight:600;color:var(--amber-glow);font-size:12px;margin-bottom:4px;">매입 #' + (pi+1) + ' (매입일자: ' + p.purchase_date + ')</div>';
            html += '<table class="data-table" style="font-size:12px;"><thead><tr><th>제품명</th><th>매입업체</th><th class="text-right">단가</th><th class="text-right" style="width:40px;">수량</th><th class="text-right">소계</th></tr></thead><tbody>';
            (p.details || []).forEach(function(pd) {
                html += '<tr><td>' + pd.product_name + '</td><td>' + (pd.vendor_name || '-') + '</td><td class="money">' + fmtM(pd.unit_price) + '</td><td class="text-right">' + pd.quantity + '</td><td class="money">' + fmtM(pd.subtotal) + '</td></tr>';
            });
            html += '</tbody></table>';
            html += '<div style="text-align:right;margin-top:3px;font-size:12px;color:var(--amber-glow);">소계: ' + fmtM(p.total_amount) + '원</div></div>';
        });
        html += '<div style="text-align:right;margin-top:6px;font-weight:700;font-size:13px;">매입 총액: <span style="color:var(--amber-glow);">' + fmtM(inv.purchase_total_amount) + '원</span> / 부가세: ' + fmtM(inv.purchase_vat_amount) + '원</div></div>';
    }
    
    // 영업이익
    var profit = (parseInt(inv.total_amount) || 0) - (parseInt(inv.purchase_total_amount) || 0);
    html += '<div style="margin-top:12px;padding:10px;border-radius:8px;background:rgba(255,255,255,0.03);text-align:center;font-size:13px;">';
    html += '<span style="margin-right:20px;">매출: <strong>' + fmtM(inv.total_amount) + '원</strong></span>';
    html += '<span style="margin-right:20px;color:var(--amber-glow);">매입: <strong>' + fmtM(inv.purchase_total_amount) + '원</strong></span>';
    html += '<span>영업이익: <strong style="color:' + (profit >= 0 ? 'var(--success)' : 'var(--danger)') + ';">' + fmtM(profit) + '원</strong></span></div>';
    
    document.getElementById('tiPopupContent').innerHTML = html;
}

function closeViewPopup() {
    document.getElementById('tiPopupOverlay').style.display = 'none';
    document.getElementById('tiPopup').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('tiPopupOverlay').addEventListener('click', closeViewPopup);
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeViewPopup(); });

JS;
?>
