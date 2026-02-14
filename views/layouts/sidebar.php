<?php $cp = getParam('page', 'dashboard'); ?>
<nav class="sidebar">
    <div class="sidebar-header">
        <div class="logo-box">
            <img src="assets/images/altnet_logo.png" alt="AltNET">
        </div>
        <div class="brand-text">AltNET ECOUNT ERP</div>
    </div>
    
    <div class="sidebar-nav">
        <a href="?page=dashboard" class="<?= $cp === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> 대시보드
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="?page=sales" class="<?= $cp === 'sales' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> 매출/매입 관리
        </a>
        <a href="?page=companies" class="<?= $cp === 'companies' ? 'active' : '' ?>">
            <i class="fas fa-building"></i> 매출 업체 관리
        </a>
        <a href="?page=vendors" class="<?= $cp === 'vendors' ? 'active' : '' ?>">
            <i class="fas fa-truck"></i> 매입 업체 관리
        </a>
        <a href="?page=items" class="<?= $cp === 'items' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i> 판매 제품 코드
        </a>
        <a href="?page=taxinvoice" class="<?= $cp === 'taxinvoice' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice"></i> 세금계산서 발행요청
        </a>
        
        <div class="nav-divider"></div>
        
        <?php if (Auth::hasRole(['admin'])): ?>
        <a href="?page=users" class="<?= $cp === 'users' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i> 사용자 관리
        </a>
        <a href="?page=backup" class="<?= $cp === 'backup' ? 'active' : '' ?>">
            <i class="fas fa-database"></i> 백업/복원
        </a>
        <?php endif; ?>
        
        <a href="?page=audit" class="<?= $cp === 'audit' ? 'active' : '' ?>">
            <i class="fas fa-history"></i> 감사 로그
        </a>
    </div>
    
    <div class="sidebar-user">
        <div>
            <span class="user-name"><i class="fas fa-user-circle"></i> <?= e(Session::get('user_name', '')) ?></span>
            <span class="badge badge-<?= e(Session::get('user_role', 'user')) ?>"><?= e(strtoupper(Session::get('user_role', ''))) ?></span>
        </div>
        <a href="?page=logout" class="logout-btn" title="로그아웃"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</nav>
