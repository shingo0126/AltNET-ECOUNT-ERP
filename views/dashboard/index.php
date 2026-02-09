<!-- Filter Bar -->
<div class="card">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" class="d-flex gap-2 flex-wrap align-center">
            <input type="hidden" name="page" value="dashboard">
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

<!-- Charts Row 1: Sales Analysis + Registration Count -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar" style="color:var(--accent)"></i> <?= $year ?>년 매출 분석</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-area" style="color:var(--primary-dark)"></i> 매출 등록 수량</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="countChart"></canvas>
            </div>
        </div>
    </div>
</div>

<?php 
$csvParams = "year={$year}&month={$month}&quarter={$quarter}&view={$viewType}";
?>

<!-- Charts Row 2: Top 20 Companies -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-building" style="color:var(--accent)"></i> 매출 업체 TOP 20</h3>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge badge-manager"><?= e($periodLabel) ?></span>
                <button class="btn btn-outline btn-sm" onclick="toggleDetail('company-detail')"><i class="fas fa-list"></i> 상세보기</button>
                <a href="?page=dashboard&action=exportCompanies&<?= $csvParams ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($topCompanies)): ?>
                <div class="empty-state"><i class="fas fa-chart-bar"></i><h4>데이터가 없습니다</h4></div>
            <?php else: ?>
                <div class="chart-container tall"><canvas id="topCompaniesChart"></canvas></div>
            <?php endif; ?>
            
            <!-- Detail List -->
            <div id="company-detail" style="display:none;margin-top:16px;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>순위</th><th>업체명</th><th class="text-right">매출총액</th></tr></thead>
                        <tbody>
                            <?php foreach ($allCompanies as $rank => $ac): ?>
                            <tr>
                                <td><strong><?= $rank + 1 ?></strong></td>
                                <td><?= e($ac['name']) ?></td>
                                <td class="money"><?= formatMoney($ac['total']) ?>원</td>
                            </tr>
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
                <span class="badge badge-manager"><?= e($periodLabel) ?></span>
                <button class="btn btn-outline btn-sm" onclick="toggleDetail('vendor-detail')"><i class="fas fa-list"></i> 상세보기</button>
                <a href="?page=dashboard&action=exportVendors&<?= $csvParams ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($topVendors)): ?>
                <div class="empty-state"><i class="fas fa-chart-bar"></i><h4>데이터가 없습니다</h4></div>
            <?php else: ?>
                <div class="chart-container tall"><canvas id="topVendorsChart"></canvas></div>
            <?php endif; ?>
            
            <!-- Detail List -->
            <div id="vendor-detail" style="display:none;margin-top:16px;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>순위</th><th>업체명</th><th class="text-right">매입총액</th></tr></thead>
                        <tbody>
                            <?php foreach ($allVendors as $rank => $av): ?>
                            <tr>
                                <td><strong><?= $rank + 1 ?></strong></td>
                                <td><?= e($av['name']) ?></td>
                                <td class="money"><?= formatMoney($av['total']) ?>원</td>
                            </tr>
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
$countAmounts = json_encode(array_values($chartCounts));
$viewTypeJs = $viewType;

$pageScript = <<<JS
const fmtKRW = v => new Intl.NumberFormat('ko-KR').format(v) + '원';

// BUG FIX: 수직형 차트(세로 바/라인)용 툴팁 - ctx.parsed.y 사용
// 값이 0인 경우도 정상 표시하기 위해 || 대신 직접 .y 참조
const tooltipVertical = {
    callbacks: {
        label: function(ctx) {
            var val = ctx.parsed.y;
            if (val === null || val === undefined) val = 0;
            return ctx.dataset.label + ': ' + fmtKRW(val);
        }
    }
};

// BUG FIX: 수평형 차트(indexAxis:'y')용 툴팁 - ctx.parsed.x 사용
// 핵심 수정: 수평 바 차트에서는 금액이 x축에 있으므로 반드시 ctx.parsed.x를 사용해야 합니다.
// 이전 코드 `ctx.parsed.y || ctx.parsed.x`는 y(인덱스 0,1,2...)가 truthy이면
// 금액(x) 대신 인덱스 값을 표시하는 심각한 버그가 있었습니다.
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

// Sales Chart (수직형 바 차트 → tooltipVertical 사용)
new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: '$viewTypeJs' === 'quarterly' ? ['1분기','2분기','3분기','4분기'] : ['1월','2월','3월','4월','5월','6월','7월','8월','9월','10월','11월','12월'],
        datasets: [{
            label: '매출액',
            data: '$viewTypeJs' === 'quarterly' ? $quarterlyAmounts : $monthlyAmounts,
            backgroundColor: 'rgba(0,119,182,.7)',
            borderColor: '#0077B6',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { tooltip: tooltipVertical, legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => (v/10000).toLocaleString() + '만' } } }
    }
});

// Count Chart (수직형 라인 차트)
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

// Top Companies Chart (수평형 바 차트 → tooltipHorizontal 사용)
var tcNames = $companyNames;
var tcTotals = $companyTotals;
if (tcNames.length > 0) {
    new Chart(document.getElementById('topCompaniesChart'), {
        type: 'bar',
        data: {
            labels: tcNames,
            datasets: [{ label: '매출액', data: tcTotals, backgroundColor: 'rgba(0,119,182,.65)', borderRadius: 3 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { tooltip: tooltipHorizontal, legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { callback: v => (v/10000).toLocaleString() + '만' } } }
        }
    });
}

// Top Vendors Chart (수평형 바 차트 → tooltipHorizontal 사용)
var tvNames = $vendorNames;
var tvTotals = $vendorTotals;
if (tvNames.length > 0) {
    new Chart(document.getElementById('topVendorsChart'), {
        type: 'bar',
        data: {
            labels: tvNames,
            datasets: [{ label: '매입액', data: tvTotals, backgroundColor: 'rgba(230,81,0,.6)', borderRadius: 3 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { tooltip: tooltipHorizontal, legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { callback: v => (v/10000).toLocaleString() + '만' } } }
        }
    });
}
JS;
?>

<style>
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"],
    div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>
