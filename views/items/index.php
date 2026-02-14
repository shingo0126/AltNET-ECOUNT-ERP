<?php
$flashMsg = Session::get('flash_message'); $flashT = Session::get('flash_type', 'success');
if ($flashMsg) { Session::remove('flash_message'); Session::remove('flash_type'); }
?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= e($flashT) ?>"><i class="fas fa-<?= $flashT === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($flashMsg) ?></div>
<?php endif; ?>

<div class="stat-grid" style="grid-template-columns:repeat(2,1fr);">
    <div class="stat-card primary">
        <div class="stat-label"><i class="fas fa-tags"></i> 등록된 총 제품 코드 수</div>
        <div class="stat-value"><?= number_format($totalCount) ?></div>
        <div class="stat-sub">개</div>
    </div>
    <div class="stat-card" style="display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;">
        <a href="?page=items&action=export" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> 코드목록</a>
        <?php if (Auth::hasRole(['admin','manager'])): ?>
        <button class="btn btn-primary btn-sm" onclick="openModal()"><i class="fas fa-plus"></i> 코드 등록</button>
        <?php endif; ?>
    </div>
</div>

<!-- Item List -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-tags" style="color:var(--indigo)"></i> 판매 제품 코드 목록</h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>구분자</th><th>구분</th><th>표현</th><th class="text-right">판매수량</th><th class="text-right">매출금액</th><th class="text-center">관리</th></tr></thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center" style="padding:40px;">등록된 제품 코드가 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <?php 
                        $itemStat = null;
                        foreach ($itemStats as $st) {
                            if ($st['id'] == $item['id']) { $itemStat = $st; break; }
                        }
                    ?>
                    <tr>
                        <td><strong><?= $item['sort_order'] ?></strong></td>
                        <td><?= e($item['name']) ?></td>
                        <td><span class="badge badge-manager"><?= e($item['sort_order'] . '.' . $item['name']) ?></span></td>
                        <td class="text-right"><?= $itemStat ? number_format($itemStat['total_quantity']) : '0' ?></td>
                        <td class="money"><?= $itemStat ? formatMoney($itemStat['total_amount']) : '0' ?>원</td>
                        <td class="text-center">
                            <?php if (Auth::hasRole(['admin','manager'])): ?>
                            <button class="btn btn-outline btn-sm" onclick="editItem(<?= $item['id'] ?>, '<?= e($item['name']) ?>')"><i class="fas fa-edit"></i></button>
                            <a href="?page=items&action=delete&id=<?= $item['id'] ?>&token=<?= e(CSRF::generate()) ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete()"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($pag['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($pag['current'] > 1): ?>
            <a href="?page=items&p=<?= $pag['current']-1 ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $pag['current']-3); $i <= min($pag['total_pages'], $pag['current']+3); $i++): ?>
            <a href="?page=items&p=<?= $i ?>" class="<?= $i == $pag['current'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pag['current'] < $pag['total_pages']): ?>
            <a href="?page=items&p=<?= $pag['current']+1 ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- TOP 15 By Quantity -->
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="fas fa-sort-amount-down" style="color:var(--indigo)"></i> 판매 수량 TOP 15</h3>
        <div class="d-flex gap-2">
            <button class="btn btn-outline btn-sm" onclick="toggleDetail('qty-detail')"><i class="fas fa-list"></i> 상세보기</button>
            <a href="?page=items&action=exportStatsQty" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($top15Qty) || array_sum(array_column($top15Qty, 'total_quantity')) == 0): ?>
            <div class="empty-state"><i class="fas fa-chart-bar"></i><h4>판매 수량 데이터가 없습니다</h4></div>
        <?php else: ?>
        <div class="chart-container" style="height:520px;"><canvas id="top15QtyChart"></canvas></div>
        <?php endif; ?>
        
        <!-- Detail list (hidden by default) -->
        <div id="qty-detail" style="display:none;margin-top:16px;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>순위</th><th>제품 코드</th><th class="text-right">판매 수량</th><th class="text-right">매출 금액</th></tr></thead>
                    <tbody>
                        <?php foreach ($allByQty as $rank => $s): ?>
                        <tr>
                            <td><strong><?= $rank + 1 ?></strong></td>
                            <td><?= e($s['sort_order'] . '.' . $s['name']) ?></td>
                            <td class="text-right"><?= number_format($s['total_quantity']) ?></td>
                            <td class="money"><?= formatMoney($s['total_amount']) ?>원</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- TOP 15 By Amount -->
<div class="card mt-3">
    <div class="card-header">
        <h3><i class="fas fa-won-sign" style="color:var(--cyan-accent)"></i> 매출 금액 TOP 15</h3>
        <div class="d-flex gap-2">
            <button class="btn btn-outline btn-sm" onclick="toggleDetail('amt-detail')"><i class="fas fa-list"></i> 상세보기</button>
            <a href="?page=items&action=exportStatsAmt" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($top15Amt) || array_sum(array_column($top15Amt, 'total_amount')) == 0): ?>
            <div class="empty-state"><i class="fas fa-chart-bar"></i><h4>매출 금액 데이터가 없습니다</h4></div>
        <?php else: ?>
        <div class="chart-container" style="height:520px;"><canvas id="top15AmtChart"></canvas></div>
        <?php endif; ?>
        
        <!-- Detail list (hidden by default) -->
        <div id="amt-detail" style="display:none;margin-top:16px;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>순위</th><th>제품 코드</th><th class="text-right">판매 수량</th><th class="text-right">매출 금액</th></tr></thead>
                    <tbody>
                        <?php foreach ($allByAmt as $rank => $s): ?>
                        <tr>
                            <td><strong><?= $rank + 1 ?></strong></td>
                            <td><?= e($s['sort_order'] . '.' . $s['name']) ?></td>
                            <td class="text-right"><?= number_format($s['total_quantity']) ?></td>
                            <td class="money"><?= formatMoney($s['total_amount']) ?>원</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="itemModal">
    <div class="modal-box" style="max-width:400px;">
        <div class="modal-header"><h4 id="modal-title">제품 코드 등록</h4><button class="modal-close" onclick="closeModal()">&times;</button></div>
        <form method="POST" action="?page=items&action=save">
            <?= CSRF::field() ?>
            <input type="hidden" name="id" id="item_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">구분 <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="f_name" class="form-control" required placeholder="예: 하드웨어 판매">
                    <small class="text-muted">구분자는 자동으로 숫자가 부여됩니다.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">취소</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 저장</button>
            </div>
        </form>
    </div>
</div>

<?php
$qtyNames = json_encode(array_map(function($s){ return $s['sort_order'].'.'.$s['name']; }, $top15Qty), JSON_UNESCAPED_UNICODE);
$qtyValues = json_encode(array_map(function($s){ return (int)$s['total_quantity']; }, $top15Qty));

$amtNames = json_encode(array_map(function($s){ return $s['sort_order'].'.'.$s['name']; }, $top15Amt), JSON_UNESCAPED_UNICODE);
$amtValues = json_encode(array_map(function($s){ return (int)$s['total_amount']; }, $top15Amt));

$pageScript = <<<JS
function openModal() {
    document.getElementById('modal-title').textContent = '제품 코드 등록';
    document.getElementById('item_id').value = '';
    document.getElementById('f_name').value = '';
    document.getElementById('itemModal').classList.add('active');
}
function editItem(id, name) {
    document.getElementById('modal-title').textContent = '제품 코드 수정';
    document.getElementById('item_id').value = id;
    document.getElementById('f_name').value = name;
    document.getElementById('itemModal').classList.add('active');
}
function closeModal() { document.getElementById('itemModal').classList.remove('active'); }

function toggleDetail(id) {
    var el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

var fmtKRW = function(v) { return new Intl.NumberFormat('ko-KR').format(v) + '원'; };

// Chart.js dark theme
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';

// TOP 15 Quantity Chart
var qtyEl = document.getElementById('top15QtyChart');
if (qtyEl) {
    new Chart(qtyEl, {
        type: 'bar',
        data: {
            labels: $qtyNames,
            datasets: [{ label: '판매 수량', data: $qtyValues, backgroundColor: 'rgba(99,102,241,.6)', borderRadius: 3 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            layout: { padding: { left: 10 } },
            plugins: { 
                tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed.x.toLocaleString() + '개'; } } },
                legend: { display: false } 
            },
            scales: {
                y: {
                    ticks: {
                        font: { size: 12, weight: 'bold' },
                        color: '#e2e8f0',
                        autoSkip: false,
                        padding: 6
                    }
                },
                x: { beginAtZero: true }
            }
        }
    });
}

// TOP 15 Amount Chart
var amtEl = document.getElementById('top15AmtChart');
if (amtEl) {
    new Chart(amtEl, {
        type: 'bar',
        data: {
            labels: $amtNames,
            datasets: [{ label: '매출 금액', data: $amtValues, backgroundColor: 'rgba(37,99,235,.55)', borderRadius: 3 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            layout: { padding: { left: 10 } },
            plugins: { 
                tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + fmtKRW(ctx.parsed.x); } } },
                legend: { display: false } 
            },
            scales: {
                y: {
                    ticks: {
                        font: { size: 12, weight: 'bold' },
                        color: '#e2e8f0',
                        autoSkip: false,
                        padding: 6
                    }
                },
                x: { beginAtZero: true, ticks: { callback: function(v) { return (v/10000).toLocaleString() + '만'; } } }
            }
        }
    });
}
JS;
?>
