<?php
$flashMsg = Session::get('flash_message'); $flashT = Session::get('flash_type', 'success');
if ($flashMsg) { Session::remove('flash_message'); Session::remove('flash_type'); }
?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= e($flashT) ?>"><i class="fas fa-<?= $flashT === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($flashMsg) ?></div>
<?php endif; ?>

<!-- Create Backup -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-database" style="color:var(--cyan-accent)"></i> 데이터베이스 백업</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="?page=backup&action=create" class="d-flex gap-2 align-center flex-wrap">
            <?= CSRF::field() ?>
            <input type="text" name="memo" class="form-control" placeholder="백업 메모 (선택)" style="max-width:400px;">
            <button type="submit" class="btn btn-primary" onclick="return confirm('데이터베이스를 백업하시겠습니까?')">
                <i class="fas fa-download"></i> 백업 생성
            </button>
        </form>
    </div>
</div>

<!-- Backup History -->
<div class="card mt-2">
    <div class="card-header"><h3><i class="fas fa-history"></i> 백업 이력</h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>#</th><th>파일명</th><th>파일크기</th><th>실행자</th><th>메모</th><th>일시</th><th class="text-center">관리</th></tr></thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:40px;">백업 이력이 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($backups as $i => $b): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($b['filename']) ?></strong></td>
                        <td><?= number_format($b['filesize'] / 1024, 1) ?> KB</td>
                        <td><?= e($b['user_name']) ?></td>
                        <td><?= e($b['memo']) ?></td>
                        <td><?= e($b['created_at']) ?></td>
                        <td class="text-center">
                            <a href="?page=backup&action=download&id=<?= $b['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> 다운로드</a>
                            <button class="btn btn-danger btn-sm" onclick="showRestoreModal(<?= $b['id'] ?>, '<?= e($b['filename']) ?>')"><i class="fas fa-undo"></i> 복원</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Restore Modal (2-step confirmation) -->
<div class="modal-overlay" id="restoreModal">
    <div class="modal-box" style="max-width:450px;">
        <div class="modal-header" style="background:rgba(239,68,68,0.12);">
            <h4 style="color:var(--rose);"><i class="fas fa-exclamation-triangle"></i> 데이터베이스 복원 경고</h4>
            <button class="modal-close" onclick="closeRestoreModal()">&times;</button>
        </div>
        <form method="POST" action="?page=backup&action=restore">
            <?= CSRF::field() ?>
            <input type="hidden" name="backup_id" id="restore_backup_id" value="">
            <div class="modal-body">
                <div class="alert alert-danger">
                    <strong>주의!</strong> 이 작업은 현재 데이터를 선택한 백업 시점으로 되돌립니다. 진행 후 취소할 수 없습니다.
                </div>
                <p>복원 파일: <strong id="restore_filename"></strong></p>
                <div class="form-group mt-2">
                    <label class="form-label">비밀번호를 입력하여 복원을 확인하세요</label>
                    <input type="password" name="password" class="form-control" required placeholder="현재 비밀번호 입력">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeRestoreModal()">취소</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-undo"></i> 복원 실행</button>
            </div>
        </form>
    </div>
</div>

<?php $pageScript = <<<'JS'
function showRestoreModal(id, filename) {
    document.getElementById('restore_backup_id').value = id;
    document.getElementById('restore_filename').textContent = filename;
    document.getElementById('restoreModal').classList.add('active');
}
function closeRestoreModal() { document.getElementById('restoreModal').classList.remove('active'); }
JS;
?>
