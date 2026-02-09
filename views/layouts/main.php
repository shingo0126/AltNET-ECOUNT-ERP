<?php
$appConfig = require __DIR__ . '/../../config/app.php';
$sessionInfo = Session::getSessionInfo();
$currentPage = getParam('page', 'dashboard');
$user = Session::getUser();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'AltNET ECOUNT ERP') ?></title>
    <?= CSRF::meta() ?>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@400;500;600;700;800&family=Roboto+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Flatpickr (Date Picker) -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
    <!-- App CSS -->
    <link href="assets/css/app.css" rel="stylesheet">
    
    <style>
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar Overlay (mobile) -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
        
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <?php include __DIR__ . '/header.php'; ?>
            
            <!-- Content -->
            <div class="content-area">
                <?php if (isset($flashMessage)): ?>
                    <div class="alert alert-<?= e($flashType ?? 'success') ?>">
                        <i class="fas fa-<?= $flashType === 'danger' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                        <?= e($flashMessage) ?>
                    </div>
                <?php endif; ?>
                
                <?= $content ?? '' ?>
            </div>
        </div>
    </div>
    
    <!-- Session Timeout Warning -->
    <div id="session-warning" data-timeout="<?= $sessionInfo['timeout'] ?>" data-warning="<?= $sessionInfo['warning'] ?>">
        <div class="sw-box">
            <div class="sw-icon"><i class="fas fa-clock"></i></div>
            <h3>세션 만료 경고</h3>
            <p>보안을 위해 곧 자동 로그아웃됩니다.</p>
            <div class="sw-time"><span id="session-countdown">5:00</span></div>
            <p style="font-size:12px;color:#999;">세션을 유지하시겠습니까?</p>
            <div class="sw-actions">
                <button class="btn btn-outline" id="session-logout"><i class="fas fa-sign-out-alt"></i> 로그아웃</button>
                <button class="btn btn-primary" id="session-extend"><i class="fas fa-refresh"></i> 세션 유지</button>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ko.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
    <script src="assets/js/app.js"></script>
    
    <?php if (isset($pageScript)): ?>
    <script><?= $pageScript ?></script>
    <?php endif; ?>
</body>
</html>
