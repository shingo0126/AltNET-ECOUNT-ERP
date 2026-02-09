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
    <div class="stat-card" style="display:flex;align-items:center;justify-content:center;gap:10px;">
        <a href="?page=items&action=export" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> 코드 다운로드</a>
        <a href="?page=items&action=exportStats" class="btn btn-success btn-sm"><i class="fas fa-chart-bar"></i> 통계 다운로드</a>
        <?php if (Auth::hasRole(['admin','manager'])): ?>
        <button class="btn btn-primary btn-sm" onclick="openModal()"><i class="fas fa-plus"></i> 등록</button>
        <?php endif; ?>
    </div>
</div>

<!-- Item List -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-tags" style="color:var(--primary-dark)"></i> 판매 제품 코드 목록</h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>구분자</th><th>구분</th><th>표현</th><th class="text-center">관리</th></tr></thead>
                <tbody>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="4" class="text-center" style="padding:40px;">등록된 제품 코드가 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?= $item['sort_order'] ?></strong></td>
                        <td><?= e($item['name']) ?></td>
                        <td><span class="badge badge-manager"><?= e($item['sort_order'] . '.' . $item['name']) ?></span></td>
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
    </div>
</div>

<!-- Analysis -->
<div class="card mt-3">
    <div class="card-header"><h3><i class="fas fa-chart-bar" style="color:var(--accent)"></i> 판매 제품 코드 분석</h3></div>
    <div class="card-body">
        <?php if (empty($itemStats)): ?>
            <div class="empty-state"><i class="fas fa-chart-pie"></i><h4>분석 데이터가 없습니다</h4></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>제품 코드</th><th class="text-right">사용 횟수</th><th class="text-right">매출 총액</th></tr></thead>
                <tbody>
                    <?php foreach ($itemStats as $s): ?>
                    <tr>
                        <td><strong><?= e($s['sort_order'] . '.' . $s['name']) ?></strong></td>
                        <td class="text-right"><?= number_format($s['usage_count']) ?>건</td>
                        <td class="money"><?= formatMoney($s['total_amount']) ?>원</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="chart-container mt-2" style="height:300px;"><canvas id="itemChart"></canvas></div>
        <?php endif; ?>
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
$statsNames = json_encode(array_map(function($s){ return $s['sort_order'].'.'.$s['name']; }, $itemStats), JSON_UNESCAPED_UNICODE);
$statsCounts = json_encode(array_column($itemStats, 'usage_count'));

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

var chartEl = document.getElementById('itemChart');
if (chartEl) {
    new Chart(chartEl, {
        type: 'doughnut',
        data: {
            labels: $statsNames,
            datasets: [{ data: $statsCounts, backgroundColor: ['#0077B6','#A7C4AA','#E89B23','#DC3545','#7FA882','#415A77','#1B263B','#F4A261','#2A9D8F','#E76F51'] }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.parsed + '건'; } } } }
        }
    });
}
JS;
?>
