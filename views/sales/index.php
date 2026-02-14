<?php
// Flash messages
$flashMsg = Session::get('flash_message');
$flashT = Session::get('flash_type', 'success');
if ($flashMsg) { Session::remove('flash_message'); Session::remove('flash_type'); }
?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= e($flashT) ?>"><i class="fas fa-<?= $flashT === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($flashMsg) ?></div>
<?php endif; ?>

<!-- ===== 공통 필터 ===== -->
<div class="card mb-2">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" class="filter-bar" style="margin:0;">
            <input type="hidden" name="page" value="sales">
            <div class="form-group">
                <select name="year" class="form-control">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y['y'] ?>" <?= $y['y'] == $year ? 'selected' : '' ?>><?= $y['y'] ?>년</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <select name="month" class="form-control">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $m ?>월</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="text" name="search" class="form-control" placeholder="업체명/매출번호 검색" value="<?= e($search) ?>">
            </div>
            <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> 검색</button>
        </form>
    </div>
</div>

<!-- ===== 1. 매출/매입 내역 리스트 ===== -->
<div class="card mb-2">
    <div class="card-header">
        <h3><i class="fas fa-file-invoice-dollar" style="color:var(--cyan-accent)"></i> 매출/매입 내역</h3>
        <div class="d-flex gap-2 flex-wrap">
            <a href="?page=sales&action=create" class="btn btn-primary"><i class="fas fa-plus"></i> 매출등록</a>
            <a href="?page=sales&action=export&year=<?= e($year) ?>&month=<?= e($month) ?>" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV 다운로드</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>매출번호</th>
                        <th>매출일자</th>
                        <th>업체명</th>
                        <th>제품코드</th>
                        <th class="text-right">매출총액</th>
                        <th class="text-right">매입총액</th>
                        <th class="text-right">영업이익</th>
                        <th class="text-center">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                    <tr><td colspan="8" class="text-center" style="padding:40px;color:var(--text-muted);">등록된 매출이 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($sales as $s): ?>
                    <?php $profit = $s['total_amount'] - $s['purchase_total']; ?>
                    <tr>
                        <td><strong><?= e($s['sale_number']) ?></strong></td>
                        <td><?= e($s['sale_date']) ?></td>
                        <td><?= e($s['company_name']) ?></td>
                        <td><?= $s['first_item_sort'] ? e($s['first_item_sort'] . '.' . $s['first_item_name']) : '-' ?></td>
                        <td class="money"><?= formatMoney($s['total_amount']) ?></td>
                        <td class="money"><?= formatMoney($s['purchase_total']) ?></td>
                        <td class="money <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatMoney($profit) ?></td>
                        <td class="text-center">
                            <a href="?page=sales&action=edit&id=<?= $s['id'] ?>" class="btn btn-outline btn-sm" title="수정"><i class="fas fa-edit"></i></a>
                            <?php if (Auth::canEdit($s['user_id'])): ?>
                            <a href="?page=sales&action=delete&id=<?= $s['id'] ?>&token=<?= e(CSRF::generate()) ?>" 
                               class="btn btn-danger btn-sm" title="삭제"
                               onclick="return confirmDelete()"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 매출/매입 내역 Pagination -->
        <?php if ($pag['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($pag['current'] > 1): ?>
            <a href="?page=sales&year=<?= $year ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&p=<?= $pag['current']-1 ?>&sp=<?= $saleSumPage ?>&pp=<?= $purchSumPage ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $pag['current']-3); $i <= min($pag['total_pages'], $pag['current']+3); $i++): ?>
            <a href="?page=sales&year=<?= $year ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&p=<?= $i ?>&sp=<?= $saleSumPage ?>&pp=<?= $purchSumPage ?>" 
               class="<?= $i == $pag['current'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pag['current'] < $pag['total_pages']): ?>
            <a href="?page=sales&year=<?= $year ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&p=<?= $pag['current']+1 ?>&sp=<?= $saleSumPage ?>&pp=<?= $purchSumPage ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== 2. 매출 집계 리스트 (업체별) ===== -->
<div class="card mb-2">
    <div class="card-header">
        <h3><i class="fas fa-chart-bar" style="color:var(--emerald)"></i> 매출 집계 <span class="badge badge-user" style="font-size:11px;margin-left:8px;"><?= e($year) ?>년 <?= e($month) ?>월</span></h3>
        <a href="?page=sales&action=exportSaleSummary&year=<?= e($year) ?>&month=<?= e($month) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV 다운로드</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:50px;">순위</th>
                        <th>업체명</th>
                        <th class="text-center">건수</th>
                        <th class="text-right">매출합계</th>
                        <th class="text-right">부가세합계</th>
                        <th class="text-right">매입합계</th>
                        <th class="text-right">영업이익</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($saleSummary)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:40px;color:var(--text-muted);">매출 집계 데이터가 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($saleSummary as $idx => $ss): ?>
                    <?php $sProfit = $ss['total_sales'] - $ss['total_purchases']; ?>
                    <tr>
                        <td><strong><?= $saleSumPag['offset'] + $idx + 1 ?></strong></td>
                        <td><strong><?= e($ss['company_name']) ?></strong></td>
                        <td class="text-center"><?= number_format($ss['sale_count']) ?></td>
                        <td class="money"><?= formatMoney($ss['total_sales']) ?></td>
                        <td class="money"><?= formatMoney($ss['total_vat']) ?></td>
                        <td class="money"><?= formatMoney($ss['total_purchases']) ?></td>
                        <td class="money <?= $sProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatMoney($sProfit) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 매출 집계 Pagination -->
        <?php if ($saleSumPag['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($saleSumPag['current'] > 1): ?>
            <a href="?page=sales&year=<?= $year ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&p=<?= $page ?>&sp=<?= $saleSumPag['current']-1 ?>&pp=<?= $purchSumPage ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $saleSumPag['current']-3); $i <= min($saleSumPag['total_pages'], $saleSumPag['current']+3); $i++): ?>
            <a href="?page=sales&year=<?= $year ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&p=<?= $page ?>&sp=<?= $i ?>&pp=<?= $purchSumPage ?>" 
               class="<?= $i == $saleSumPag['current'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($saleSumPag['current'] < $saleSumPag['total_pages']): ?>
            <a href="?page=sales&year=<?= $year ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&p=<?= $page ?>&sp=<?= $saleSumPag['current']+1 ?>&pp=<?= $purchSumPage ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== 3. 매입 집계 리스트 (업체별) ===== -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chart-pie" style="color:var(--amber-glow)"></i> 매입 집계 <span class="badge badge-user" style="font-size:11px;margin-left:8px;"><?= e($year) ?>년 <?= e($month) ?>월</span></h3>
        <a href="?page=sales&action=exportPurchSummary&year=<?= e($year) ?>&month=<?= e($month) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> CSV 다운로드</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:50px;">순위</th>
                        <th>업체명</th>
                        <th class="text-center">건수</th>
                        <th class="text-right">매입합계</th>
                        <th class="text-right">부가세합계</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchSummary)): ?>
                    <tr><td colspan="5" class="text-center" style="padding:40px;color:var(--text-muted);">매입 집계 데이터가 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($purchSummary as $idx => $ps): ?>
                    <tr>
                        <td><strong><?= $purchSumPag['offset'] + $idx + 1 ?></strong></td>
                        <td><strong><?= e($ps['vendor_name']) ?></strong></td>
                        <td class="text-center"><?= number_format($ps['purchase_count']) ?></td>
                        <td class="money"><?= formatMoney($ps['total_purchases']) ?></td>
                        <td class="money"><?= formatMoney($ps['total_vat']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 매입 집계 Pagination -->
        <?php if ($purchSumPag['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($purchSumPag['current'] > 1): ?>
            <a href="?page=sales&year=<?= $year ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&p=<?= $page ?>&sp=<?= $saleSumPage ?>&pp=<?= $purchSumPag['current']-1 ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $purchSumPag['current']-3); $i <= min($purchSumPag['total_pages'], $purchSumPag['current']+3); $i++): ?>
            <a href="?page=sales&year=<?= $year ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&p=<?= $page ?>&sp=<?= $saleSumPage ?>&pp=<?= $i ?>" 
               class="<?= $i == $purchSumPag['current'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($purchSumPag['current'] < $purchSumPag['total_pages']): ?>
            <a href="?page=sales&year=<?= $year ?>&month=<?= $month ?>&search=<?= urlencode($search) ?>&p=<?= $page ?>&sp=<?= $saleSumPage ?>&pp=<?= $purchSumPag['current']+1 ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
