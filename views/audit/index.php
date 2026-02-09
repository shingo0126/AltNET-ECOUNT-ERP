<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history" style="color:var(--accent)"></i> 감사 로그</h3>
        <a href="?page=audit&action=export" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV 다운로드</a>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" class="filter-bar mb-2">
            <input type="hidden" name="page" value="audit">
            <div class="form-group">
                <input type="text" name="user" class="form-control" placeholder="사용자" value="<?= e($filterUser) ?>" style="width:120px;">
            </div>
            <div class="form-group">
                <select name="act" class="form-control">
                    <option value="">전체 액션</option>
                    <?php foreach (['INSERT','UPDATE','DELETE','LOGIN','LOGOUT','BACKUP','RESTORE','EXPORT'] as $a): ?>
                    <option value="<?= $a ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="date" name="from" class="form-control" value="<?= e($filterDateFrom) ?>" style="width:150px;">
            </div>
            <div class="form-group">
                <input type="date" name="to" class="form-control" value="<?= e($filterDateTo) ?>" style="width:150px;">
            </div>
            <button class="btn btn-outline"><i class="fas fa-search"></i></button>
        </form>
        
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>일시</th><th>사용자</th><th>액션</th><th>테이블</th><th>ID</th><th>설명</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:40px;">로그가 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td style="white-space:nowrap;font-size:12px;"><?= e($l['created_at']) ?></td>
                        <td><strong><?= e($l['username']) ?></strong></td>
                        <td>
                            <?php 
                            $colors = ['INSERT'=>'success','UPDATE'=>'accent','DELETE'=>'danger','LOGIN'=>'primary-dark','LOGOUT'=>'text-muted','BACKUP'=>'warning','RESTORE'=>'danger','EXPORT'=>'accent'];
                            $c = $colors[$l['action']] ?? 'text';
                            ?>
                            <span style="color:var(--<?= $c ?>);font-weight:700;font-size:12px;"><?= e($l['action']) ?></span>
                        </td>
                        <td><?= e($l['table_name']) ?></td>
                        <td><?= e($l['record_id']) ?></td>
                        <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;"><?= e($l['description']) ?></td>
                        <td style="font-size:12px;"><?= e($l['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($pag['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($pag['current'] > 1): ?>
            <a href="?page=audit&user=<?= urlencode($filterUser) ?>&act=<?= urlencode($filterAction) ?>&from=<?= urlencode($filterDateFrom) ?>&to=<?= urlencode($filterDateTo) ?>&p=<?= $pag['current']-1 ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = max(1, $pag['current']-3); $i <= min($pag['total_pages'], $pag['current']+3); $i++): ?>
            <a href="?page=audit&user=<?= urlencode($filterUser) ?>&act=<?= urlencode($filterAction) ?>&from=<?= urlencode($filterDateFrom) ?>&to=<?= urlencode($filterDateTo) ?>&p=<?= $i ?>" class="<?= $i == $pag['current'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pag['current'] < $pag['total_pages']): ?>
            <a href="?page=audit&user=<?= urlencode($filterUser) ?>&act=<?= urlencode($filterAction) ?>&from=<?= urlencode($filterDateFrom) ?>&to=<?= urlencode($filterDateTo) ?>&p=<?= $pag['current']+1 ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
