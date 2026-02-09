<?php
$flashMsg = Session::get('flash_message'); $flashT = Session::get('flash_type', 'success');
if ($flashMsg) { Session::remove('flash_message'); Session::remove('flash_type'); }
?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= e($flashT) ?>"><i class="fas fa-<?= $flashT === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($flashMsg) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users-cog" style="color:var(--accent)"></i> 사용자 목록</h3>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> 사용자 등록</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>사용자명</th><th>이름</th><th>이메일</th><th>역할</th><th>상태</th><th>최근 로그인</th><th class="text-center">관리</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($u['username']) ?></strong></td>
                        <td><?= e($u['name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="badge badge-<?= e($u['role']) ?>"><?= strtoupper(e($u['role'])) ?></span></td>
                        <td><?= $u['is_active'] ? '<span class="text-success">활성</span>' : '<span class="text-danger">비활성</span>' ?></td>
                        <td><?= $u['last_login'] ? e($u['last_login']) : '-' ?></td>
                        <td class="text-center">
                            <button class="btn btn-outline btn-sm" onclick='editUser(<?= json_encode($u) ?>)'><i class="fas fa-edit"></i></button>
                            <?php if ($u['id'] != Session::getUserId()): ?>
                            <a href="?page=users&action=delete&id=<?= $u['id'] ?>&token=<?= e(CSRF::generate()) ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('이 사용자를 비활성화 하시겠습니까?')"><i class="fas fa-user-slash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-header"><h3><i class="fas fa-shield-alt"></i> 권한 안내</h3></div>
    <div class="card-body">
        <table class="data-table">
            <thead><tr><th>역할</th><th>권한 범위</th></tr></thead>
            <tbody>
                <tr><td><span class="badge badge-admin">ADMIN</span></td><td>전체 권한 (사용자 관리, 백업/복원, 모든 데이터 CRUD)</td></tr>
                <tr><td><span class="badge badge-manager">MANAGER</span></td><td>매출/매입 CRUD + 업체/제품코드 관리</td></tr>
                <tr><td><span class="badge badge-user">USER</span></td><td>매출/매입 조회 + 본인 등록건만 수정/삭제</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="userModal">
    <div class="modal-box">
        <div class="modal-header"><h4 id="modal-title">사용자 등록</h4><button class="modal-close" onclick="closeModal()">&times;</button></div>
        <form method="POST" action="?page=users&action=save">
            <?= CSRF::field() ?>
            <input type="hidden" name="id" id="user_id" value="">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">사용자명 <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="f_username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">이름 <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="f_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">이메일</label>
                    <input type="email" name="email" id="f_email" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">비밀번호 <small class="text-muted">(수정시 비워두면 유지)</small></label>
                    <input type="password" name="password" id="f_password" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">역할</label>
                    <select name="role" id="f_role" class="form-control">
                        <option value="user">USER (일반사용자)</option>
                        <option value="manager">MANAGER (매니저)</option>
                        <option value="admin">ADMIN (관리자)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">취소</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 저장</button>
            </div>
        </form>
    </div>
</div>

<?php $pageScript = <<<'JS'
function openModal() {
    document.getElementById('modal-title').textContent = '사용자 등록';
    document.getElementById('user_id').value = '';
    document.getElementById('f_username').value = '';
    document.getElementById('f_name').value = '';
    document.getElementById('f_email').value = '';
    document.getElementById('f_password').value = '';
    document.getElementById('f_role').value = 'user';
    document.getElementById('f_username').readOnly = false;
    document.getElementById('userModal').classList.add('active');
}
function editUser(u) {
    document.getElementById('modal-title').textContent = '사용자 수정';
    document.getElementById('user_id').value = u.id;
    document.getElementById('f_username').value = u.username;
    document.getElementById('f_name').value = u.name;
    document.getElementById('f_email').value = u.email || '';
    document.getElementById('f_password').value = '';
    document.getElementById('f_role').value = u.role;
    document.getElementById('f_username').readOnly = true;
    document.getElementById('userModal').classList.add('active');
}
function closeModal() { document.getElementById('userModal').classList.remove('active'); }
JS;
?>
