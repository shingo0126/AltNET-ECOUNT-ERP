<?php
$flashMsg = Session::get('flash_message'); $flashT = Session::get('flash_type', 'success');
if ($flashMsg) { Session::remove('flash_message'); Session::remove('flash_type'); }
?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= e($flashT) ?>"><i class="fas fa-<?= $flashT === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= e($flashMsg) ?></div>
<?php endif; ?>

<div class="stat-grid" style="grid-template-columns:repeat(2,1fr);">
    <div class="stat-card accent">
        <div class="stat-label"><i class="fas fa-building"></i> 등록된 총 업체수</div>
        <div class="stat-value"><?= number_format($totalCount) ?></div>
        <div class="stat-sub">개사</div>
    </div>
    <div class="stat-card" style="display:flex;align-items:center;justify-content:center;gap:12px;">
        <a href="?page=companies&action=export" class="btn btn-success"><i class="fas fa-file-csv"></i> CSV 다운로드</a>
        <?php if (Auth::hasRole(['admin','manager'])): ?>
        <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> 업체 등록</button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-building" style="color:var(--cyan-accent)"></i> 매출 업체 목록</h3>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="page" value="companies">
            <input type="text" name="search" class="form-control" placeholder="업체명/담당자/연락처 검색" value="<?= e($search) ?>" style="width:220px;">
            <button class="btn btn-outline btn-sm"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th><th>업체명</th><th>담당자</th><th>연락처</th><th>이메일</th><th>주소</th><th class="text-center">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($companies)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:40px;color:var(--text-muted);">등록된 업체가 없습니다.</td></tr>
                    <?php else: ?>
                    <?php foreach ($companies as $i => $c): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td><?= e($c['contact_person']) ?></td>
                        <td><?= e($c['phone']) ?></td>
                        <td><?= e($c['email']) ?></td>
                        <td><?= e($c['address']) ?> <?= e($c['address_detail']) ?></td>
                        <td class="text-center">
                            <?php if (Auth::hasRole(['admin','manager'])): ?>
                            <button class="btn btn-outline btn-sm" onclick="editCompany(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                            <a href="?page=companies&action=delete&id=<?= $c['id'] ?>&token=<?= e(CSRF::generate()) ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('이 업체를 삭제하시겠습니까?')"><i class="fas fa-trash"></i></a>
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

<!-- Modal -->
<div class="modal-overlay" id="companyModal">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <h4 id="modal-title">업체 등록</h4>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" action="?page=companies&action=save">
            <?= CSRF::field() ?>
            <input type="hidden" name="id" id="company_id" value="">
            <div class="modal-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">업체명 <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="f_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">담당자</label>
                        <input type="text" name="contact_person" id="f_contact" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">연락처</label>
                        <input type="text" name="phone" id="f_phone" class="form-control input-phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">이메일</label>
                        <input type="email" name="email" id="f_email" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">주소</label>
                    <div class="d-flex gap-2 mb-1">
                        <input type="text" name="zipcode" id="f_zipcode" class="form-control" placeholder="우편번호" readonly style="width:120px;">
                        <button type="button" class="btn btn-outline" onclick="searchAddress('f_zipcode','f_address','f_address_detail')"><i class="fas fa-search"></i> 우편번호 검색</button>
                    </div>
                    <input type="text" name="address" id="f_address" class="form-control mb-1" placeholder="주소" readonly>
                    <input type="text" name="address_detail" id="f_address_detail" class="form-control" placeholder="상세주소">
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
    document.getElementById('modal-title').textContent = '업체 등록';
    document.getElementById('company_id').value = '';
    document.getElementById('f_name').value = '';
    document.getElementById('f_contact').value = '';
    document.getElementById('f_phone').value = '010-';
    document.getElementById('f_email').value = '';
    document.getElementById('f_zipcode').value = '';
    document.getElementById('f_address').value = '';
    document.getElementById('f_address_detail').value = '';
    document.getElementById('companyModal').classList.add('active');
}

function editCompany(c) {
    document.getElementById('modal-title').textContent = '업체 수정';
    document.getElementById('company_id').value = c.id;
    document.getElementById('f_name').value = c.name || '';
    document.getElementById('f_contact').value = c.contact_person || '';
    document.getElementById('f_phone').value = c.phone || '010-';
    document.getElementById('f_email').value = c.email || '';
    document.getElementById('f_zipcode').value = c.zipcode || '';
    document.getElementById('f_address').value = c.address || '';
    document.getElementById('f_address_detail').value = c.address_detail || '';
    document.getElementById('companyModal').classList.add('active');
}

function closeModal() {
    document.getElementById('companyModal').classList.remove('active');
}

initPhoneInputs();
JS;
?>
