<!-- Filter Bar (상단 요약 통계용) -->
<div class="card">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" class="d-flex gap-2 flex-wrap align-center">
            <input type="hidden" name="page" value="dashboard">
            <input type="hidden" name="top_year" value="<?= e($topYear) ?>">
            <input type="hidden" name="top_view" value="<?= e($topView) ?>">
            <input type="hidden" name="top_month" value="<?= e($topMonth) ?>">
            <input type="hidden" name="top_quarter" value="<?= e($topQuarter) ?>">
            <div class="form-group mb-0">
                <select name="year" class="form-control" onchange="this.form.submit()">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y['y'] ?>" <?= $y['y'] == $year ? 'selected' : '' ?>><?= $y['y'] ?>년</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <select name="view" class="form-control" onchange="this.form.submit()">
                    <option value="monthly" <?= $viewType === 'monthly' ? 'selected' : '' ?>>월별</option>
                    <option value="quarterly" <?= $viewType === 'quarterly' ? 'selected' : '' ?>>분기별</option>
                </select>
            </div>
            <?php if ($viewType === 'monthly'): ?>
            <div class="form-group mb-0">
                <select name="month" class="form-control" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $m ?>월</option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="form-group mb-0">
                <select name="quarter" class="form-control" onchange="this.form.submit()">
                    <option value="1" <?= $quarter == '1' ? 'selected' : '' ?>>1분기 (1~3월)</option>
                    <option value="2" <?= $quarter == '2' ? 'selected' : '' ?>>2분기 (4~6월)</option>
                    <option value="3" <?= $quarter == '3' ? 'selected' : '' ?>>3분기 (7~9월)</option>
                    <option value="4" <?= $quarter == '4' ? 'selected' : '' ?>>4분기 (10~12월)</option>
                </select>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stat-grid">
    <div class="stat-card accent">
        <div class="stat-label"><i class="fas fa-chart-line"></i> 매출 총액 (<?= $viewType === 'monthly' ? "{$month}월" : "{$quarter}분기" ?>)</div>
        <div class="stat-value"><?= formatMoney($monthlySales['total']) ?></div>
        <div class="stat-sub">원</div>
    </div>
    <div class="stat-card primary">
        <div class="stat-label"><i class="fas fa-file-alt"></i> 매출 등록 건수</div>
        <div class="stat-value"><?= number_format($monthlySales['cnt']) ?></div>
        <div class="stat-sub">건</div>
    </div>
    <div class="stat-card warning">
        <div class="stat-label"><i class="fas fa-shopping-cart"></i> 매입 총액</div>
        <div class="stat-value"><?= formatMoney($monthlyPurchases['total']) ?></div>
        <div class="stat-sub">원</div>
    </div>
    <div class="stat-card success">
        <div class="stat-label"><i class="fas fa-coins"></i> 영업이익</div>
        <div class="stat-value"><?= formatMoney($monthlySales['total'] - $monthlyPurchases['total']) ?></div>
        <div class="stat-sub">원</div>
    </div>
</div>

<!-- Charts Row 1: 매출/매입 분석 + 등록 수량 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar" style="color:var(--accent)"></i> <?= e($year) ?>년 매출/매입 분석</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-area" style="color:var(--primary-dark)"></i> <?= e($year) ?>년 매출 등록 수량</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="countChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- TOP 20 독립 필터 바 -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:12px 20px;">
        <div class="d-flex gap-2 flex-wrap align-center">
            <span style="font-weight:600;color:var(--text);margin-right:8px;white-space:nowrap;">
                <i class="fas fa-filter" style="color:var(--accent)"></i> TOP 20 조회 기간
            </span>
            <div class="form-group mb-0">
                <select id="topYear" class="form-control" onchange="onTopFilterChange()">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y['y'] ?>" <?= $y['y'] == $topYear ? 'selected' : '' ?>><?= $y['y'] ?>년</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <select id="topView" class="form-control" onchange="onTopViewChange()">
                    <option value="yearly" <?= $topView === 'yearly' ? 'selected' : '' ?>>년도 전체</option>
                    <option value="quarterly" <?= $topView === 'quarterly' ? 'selected' : '' ?>>분기별</option>
                    <option value="monthly" <?= $topView === 'monthly' ? 'selected' : '' ?>>월별</option>
                </select>
            </div>
            <div class="form-group mb-0" id="topQuarterWrap" style="display:<?= $topView === 'quarterly' ? 'block' : 'none' ?>;">
                <select id="topQuarter" class="form-control" onchange="onTopFilterChange()">
                    <option value="1" <?= $topQuarter == '1' ? 'selected' : '' ?>>1분기 (1~3월)</option>
                    <option value="2" <?= $topQuarter == '2' ? 'selected' : '' ?>>2분기 (4~6월)</option>
                    <option value="3" <?= $topQuarter == '3' ? 'selected' : '' ?>>3분기 (7~9월)</option>
                    <option value="4" <?= $topQuarter == '4' ? 'selected' : '' ?>>4분기 (10~12월)</option>
                </select>
            </div>
            <div class="form-group mb-0" id="topMonthWrap" style="display:<?= $topView === 'monthly' ? 'block' : 'none' ?>;">
                <select id="topMonth" class="form-control" onchange="onTopFilterChange()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $topMonth ? 'selected' : '' ?>><?= $m ?>월</option>
                    <?php endfor; ?>
                </select>
            </div>
            <span id="topPeriodBadge" class="badge badge-manager" style="margin-left:8px;"><?= e($topPeriodLabel) ?></span>
        </div>
    </div>
</div>

<!-- Charts Row 2: Top 20 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-building" style="color:var(--accent)"></i> 매출 업체 TOP 20</h3>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline btn-sm" onclick="toggleDetail('company-detail')"><i class="fas fa-list"></i> 상세보기</button>
                <a id="csvCompanyLink" href="?page=dashboard&action=exportCompanies&top_year=<?= e($topYear) ?>&top_view=<?= e($topView) ?>&top_month=<?= e($topMonth) ?>&top_quarter=<?= e($topQuarter) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
            </div>
        </div>
        <div class="card-body">
            <div id="companyChartWrap">
                <?php if (empty($topCompanies)): ?>
                    <div class="empty-state"><i class="fas fa-chart-bar"></i><h4>데이터가 없습니다</h4></div>
                <?php else: ?>
                    <div class="chart-container tall"><canvas id="topCompaniesChart"></canvas></div>
                <?php endif; ?>
            </div>
            <div id="company-detail" style="display:none;margin-top:16px;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>순위</th><th>업체명</th><th class="text-right">매출총액</th></tr></thead>
                        <tbody id="companyDetailBody">
                            <?php foreach ($allCompanies as $rank => $ac): ?>
                            <tr><td><strong><?= $rank + 1 ?></strong></td><td><?= e($ac['name']) ?></td><td class="money"><?= formatMoney($ac['total']) ?>원</td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-truck" style="color:#E65100"></i> 매입 업체 TOP 20</h3>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline btn-sm" onclick="toggleDetail('vendor-detail')"><i class="fas fa-list"></i> 상세보기</button>
                <a id="csvVendorLink" href="?page=dashboard&action=exportVendors&top_year=<?= e($topYear) ?>&top_view=<?= e($topView) ?>&top_month=<?= e($topMonth) ?>&top_quarter=<?= e($topQuarter) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
            </div>
        </div>
        <div class="card-body">
            <div id="vendorChartWrap">
                <?php if (empty($topVendors)): ?>
                    <div class="empty-state"><i class="fas fa-chart-bar"></i><h4>데이터가 없습니다</h4></div>
                <?php else: ?>
                    <div class="chart-container tall"><canvas id="topVendorsChart"></canvas></div>
                <?php endif; ?>
            </div>
            <div id="vendor-detail" style="display:none;margin-top:16px;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>순위</th><th>업체명</th><th class="text-right">매입총액</th></tr></thead>
                        <tbody id="vendorDetailBody">
                            <?php foreach ($allVendors as $rank => $av): ?>
                            <tr><td><strong><?= $rank + 1 ?></strong></td><td><?= e($av['name']) ?></td><td class="money"><?= formatMoney($av['total']) ?>원</td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$companyNames = json_encode(array_column($topCompanies, 'name'), JSON_UNESCAPED_UNICODE);
$companyTotals = json_encode(array_map('intval', array_column($topCompanies, 'total')));
$vendorNames = json_encode(array_column($topVendors, 'name'), JSON_UNESCAPED_UNICODE);
$vendorTotals = json_encode(array_map('intval', array_column($topVendors, 'total')));
$monthlyAmounts = json_encode(array_values($chartMonthly));
$quarterlyAmounts = json_encode(array_values($chartQuarterly));
$monthlyPurchaseAmounts = json_encode(array_values($chartMonthlyPurchase));
$quarterlyPurchaseAmounts = json_encode(array_values($chartQuarterlyPurchase));
$countAmounts = json_encode(array_values($chartCounts));
$viewTypeJs = $viewType;

$pageScript = <<<JS
const fmtKRW = v => new Intl.NumberFormat('ko-KR').format(v) + '원';
const fmtMoney = v => new Intl.NumberFormat('ko-KR').format(parseInt(v));

const tooltipVertical = {
    callbacks: {
        label: function(ctx) {
            var val = ctx.parsed.y;
            if (val === null || val === undefined) val = 0;
            return ctx.dataset.label + ': ' + fmtKRW(val);
        }
    }
};
const tooltipHorizontal = {
    callbacks: {
        label: function(ctx) {
            var val = ctx.parsed.x;
            if (val === null || val === undefined) val = 0;
            return ctx.dataset.label + ': ' + fmtKRW(val);
        }
    }
};

function toggleDetail(id) {
    var el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// ===== 매출/매입 분석 차트 (2개 데이터셋) =====
new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: '$viewTypeJs' === 'quarterly' 
            ? ['1분기','2분기','3분기','4분기'] 
            : ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'],
        datasets: [
            {
                label: '매출액',
                data: '$viewTypeJs' === 'quarterly' ? $quarterlyAmounts : $monthlyAmounts,
                backgroundColor: 'rgba(0,119,182,.7)',
                borderColor: '#0077B6',
                borderWidth: 1,
                borderRadius: 4
            },
            {
                label: '매입액',
                data: '$viewTypeJs' === 'quarterly' ? $quarterlyPurchaseAmounts : $monthlyPurchaseAmounts,
                backgroundColor: 'rgba(230,81,0,.55)',
                borderColor: '#E65100',
                borderWidth: 1,
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { 
            tooltip: tooltipVertical, 
            legend: { display: true, position: 'top', labels: { boxWidth: 14, padding: 15, font: { size: 12 } } }
        },
        scales: { y: { beginAtZero: true, ticks: { callback: v => (v/10000).toLocaleString() + '만' } } }
    }
});

// ===== 등록 수량 차트 =====
new Chart(document.getElementById('countChart'), {
    type: 'line',
    data: {
        labels: ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'],
        datasets: [{
            label: '등록 수',
            data: $countAmounts,
            borderColor: '#7FA882',
            backgroundColor: 'rgba(167,196,170,.2)',
            fill: true, tension: .3, pointRadius: 5, pointBackgroundColor: '#7FA882'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { tooltip: { callbacks: { label: function(ctx) { return (ctx.parsed.y || 0) + '건'; } } }, legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// ===== TOP20 차트 =====
var companyChart = null;
var vendorChart = null;

function createCompanyChart(names, totals) {
    var wrap = document.getElementById('companyChartWrap');
    if (names.length === 0) { wrap.innerHTML = '<div class="empty-state"><i class="fas fa-chart-bar"></i><h4>데이터가 없습니다</h4></div>'; companyChart = null; return; }
    wrap.innerHTML = '<div class="chart-container tall"><canvas id="topCompaniesChart"></canvas></div>';
    companyChart = new Chart(document.getElementById('topCompaniesChart'), {
        type: 'bar', data: { labels: names, datasets: [{ label: '매출액', data: totals, backgroundColor: 'rgba(0,119,182,.65)', borderRadius: 3 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { tooltip: tooltipHorizontal, legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => (v/10000).toLocaleString() + '만' } } } }
    });
}
function createVendorChart(names, totals) {
    var wrap = document.getElementById('vendorChartWrap');
    if (names.length === 0) { wrap.innerHTML = '<div class="empty-state"><i class="fas fa-chart-bar"></i><h4>데이터가 없습니다</h4></div>'; vendorChart = null; return; }
    wrap.innerHTML = '<div class="chart-container tall"><canvas id="topVendorsChart"></canvas></div>';
    vendorChart = new Chart(document.getElementById('topVendorsChart'), {
        type: 'bar', data: { labels: names, datasets: [{ label: '매입액', data: totals, backgroundColor: 'rgba(230,81,0,.6)', borderRadius: 3 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { tooltip: tooltipHorizontal, legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { callback: v => (v/10000).toLocaleString() + '만' } } } }
    });
}

createCompanyChart($companyNames, $companyTotals);
createVendorChart($vendorNames, $vendorTotals);

// ===== TOP20 독립 필터 핸들러 =====
function getTopFilterParams() {
    return { top_year: document.getElementById('topYear').value, top_view: document.getElementById('topView').value, top_month: document.getElementById('topMonth').value, top_quarter: document.getElementById('topQuarter').value };
}
function onTopViewChange() {
    var v = document.getElementById('topView').value;
    document.getElementById('topQuarterWrap').style.display = v === 'quarterly' ? 'block' : 'none';
    document.getElementById('topMonthWrap').style.display = v === 'monthly' ? 'block' : 'none';
    onTopFilterChange();
}
function onTopFilterChange() {
    var p = getTopFilterParams();
    var qs = 'page=dashboard&action=topData&top_year=' + p.top_year + '&top_view=' + p.top_view + '&top_month=' + p.top_month + '&top_quarter=' + p.top_quarter;
    document.getElementById('csvCompanyLink').href = '?page=dashboard&action=exportCompanies&top_year=' + p.top_year + '&top_view=' + p.top_view + '&top_month=' + p.top_month + '&top_quarter=' + p.top_quarter;
    document.getElementById('csvVendorLink').href = '?page=dashboard&action=exportVendors&top_year=' + p.top_year + '&top_view=' + p.top_view + '&top_month=' + p.top_month + '&top_quarter=' + p.top_quarter;
    updateHiddenFields(p);
    fetch('?' + qs).then(function(r) { return r.json(); }).then(function(data) {
        document.getElementById('topPeriodBadge').textContent = data.periodLabel;
        if (companyChart) { companyChart.destroy(); companyChart = null; }
        if (vendorChart) { vendorChart.destroy(); vendorChart = null; }
        createCompanyChart(data.companyNames, data.companyTotals);
        createVendorChart(data.vendorNames, data.vendorTotals);
        updateDetailTable('companyDetailBody', data.allCompanies);
        updateDetailTable('vendorDetailBody', data.allVendors);
    }).catch(function(err) { console.error('TOP20 데이터 로드 실패:', err); });
}
function updateHiddenFields(p) {
    var form = document.querySelector('form');
    if (!form) return;
    for (var key in p) { var input = form.querySelector('input[name="' + key + '"]'); if (input) input.value = p[key]; }
}
function updateDetailTable(tbodyId, dataArr) {
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    if (!dataArr || dataArr.length === 0) { tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:20px;">데이터가 없습니다</td></tr>'; return; }
    var html = '';
    for (var i = 0; i < dataArr.length; i++) { var d = dataArr[i]; html += '<tr><td><strong>' + (i+1) + '</strong></td><td>' + escapeHtml(d.name) + '</td><td class="money">' + fmtMoney(d.total) + '원</td></tr>'; }
    tbody.innerHTML = html;
}
function escapeHtml(str) { var div = document.createElement('div'); div.appendChild(document.createTextNode(str || '')); return div.innerHTML; }
JS;
?>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"],
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>
