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
    <!-- 2.0: Background Blobs -->
    <div class="bg-blob b1"></div>
    <div class="bg-blob b2"></div>
    <div class="bg-blob b3"></div>

    <!-- 2.0: Floating Particles -->
    <div class="particle" style="left:12%;animation-duration:13s;animation-delay:0s;width:2px;height:2px"></div>
    <div class="particle" style="left:28%;animation-duration:17s;animation-delay:-3s"></div>
    <div class="particle" style="left:48%;animation-duration:15s;animation-delay:-6s;width:4px;height:4px;background:rgba(99,102,241,0.3)"></div>
    <div class="particle" style="left:68%;animation-duration:19s;animation-delay:-9s"></div>
    <div class="particle" style="left:85%;animation-duration:14s;animation-delay:-2s;width:2px;height:2px;background:rgba(37,99,235,0.4)"></div>

    <div class="login-wrapper">
        <div class="login-card" id="loginCard">
            <!-- 2.0: Accent line -->
            <div class="accent-line"></div>
            <!-- 2.0: Mouse-tracking light -->
            <div class="light-track" id="lightTrack"></div>

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
                        <i class="fas fa-user" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);z-index:2;transition:color .3s;"></i>
                        <input type="text" name="username" class="form-control" placeholder="사용자명" 
                               value="<?= e(postParam('username', '')) ?>"
                               style="padding-left:40px;" autofocus autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <div style="position:relative;">
                        <i class="fas fa-lock" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);z-index:2;transition:color .3s;"></i>
                        <input type="password" name="password" class="form-control" placeholder="비밀번호"
                               style="padding-left:40px;" autocomplete="current-password">
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

    <script>
    // 2.0: Mouse-tracking light + tilt for login card
    (function() {
        var card = document.getElementById('loginCard');
        var light = document.getElementById('lightTrack');
        if (!card || !light) return;

        card.addEventListener('mousemove', function(e) {
            var rect = card.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            light.style.background = 'radial-gradient(400px circle at ' + x + 'px ' + y + 'px, rgba(34,211,238,0.06), transparent 60%)';

            var cx = rect.left + rect.width / 2;
            var cy = rect.top + rect.height / 2;
            var dx = (e.clientX - cx) / (rect.width / 2);
            var dy = (e.clientY - cy) / (rect.height / 2);
            card.style.transform = 'perspective(1000px) rotateY(' + (dx * 2) + 'deg) rotateX(' + (-dy * 2) + 'deg)';
        });
        card.addEventListener('mouseleave', function() {
            card.style.transform = 'perspective(1000px) rotateY(0deg) rotateX(0deg)';
            card.style.transition = 'transform 0.5s ease';
            setTimeout(function() { card.style.transition = ''; }, 500);
        });

        // Focus glow on inputs
        document.querySelectorAll('.login-card input').forEach(function(input) {
            input.addEventListener('focus', function() {
                var icon = this.parentElement.querySelector('i');
                if (icon) icon.style.color = 'var(--cyan-accent)';
            });
            input.addEventListener('blur', function() {
                var icon = this.parentElement.querySelector('i');
                if (icon) icon.style.color = 'var(--text-muted)';
            });
        });
    })();
    </script>
</body>
</html>
