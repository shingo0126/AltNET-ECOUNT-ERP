<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - AltNET ECOUNT ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <h1>AltNET ECOUNT ERP</h1>
            <p class="login-sub">업무 관리 시스템에 로그인하세요</p>
            
            <?php if (!empty($error)): ?>
                <div class="login-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="?page=login" autocomplete="off">
                <div class="form-group">
                    <div style="position:relative;">
                        <i class="fas fa-user" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                        <input type="text" name="username" class="form-control" placeholder="사용자명" 
                               value="<?= e(postParam('username', '')) ?>"
                               style="padding-left:40px;" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <div style="position:relative;">
                        <i class="fas fa-lock" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                        <input type="password" name="password" class="form-control" placeholder="비밀번호"
                               style="padding-left:40px;">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:8px;">
                    <i class="fas fa-sign-in-alt"></i> 로그인
                </button>
            </form>
            
            <div class="login-logo">
                <img src="assets/images/altnet_logo.png" alt="AltNET - Professional Solutions Provider">
            </div>
        </div>
    </div>
</body>
</html>
