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
    <style>
    /* ===== BUBBLE CANVAS ===== */
    #bubbleCanvas {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        z-index: 1;
        pointer-events: none;
    }
    /* login-card가 캔버스 위에 오도록 */
    .login-wrapper { z-index: 2; position: relative; }
    </style>
</head>
<body>
    <!-- 2.0: Background Blobs -->
    <div class="bg-blob b1"></div>
    <div class="bg-blob b2"></div>
    <div class="bg-blob b3"></div>

    <!-- Bubble Canvas (물방울 배경) -->
    <canvas id="bubbleCanvas"></canvas>

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
                <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:8px;justify-content:center;">
                    <i class="fas fa-sign-in-alt"></i> 로그인
                </button>
            </form>
            
            <div class="login-logo">
                <img src="assets/images/altnet_logo.png" alt="AltNET - Professional Solutions Provider">
            </div>
        </div>
    </div>

    <script>
    // ===== RISING BUBBLES (Canvas Animation) =====
    (function() {
        var canvas = document.getElementById('bubbleCanvas');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var W, H;
        var bubbles = [];
        var BUBBLE_COUNT = 35;

        // 색상 팔레트 (기존 테마 조화: 블루/시안/인디고)
        var colors = [
            { r: 37,  g: 99,  b: 235 },  // blue-primary
            { r: 34,  g: 211, b: 238 },  // cyan-accent
            { r: 99,  g: 102, b: 241 },  // indigo
            { r: 16,  g: 185, b: 129 },  // emerald
            { r: 59,  g: 130, b: 246 },  // blue-hover
        ];

        function resize() {
            W = canvas.width = window.innerWidth;
            H = canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resize);
        resize();

        // 물방울 생성
        function createBubble(startFromBottom) {
            var color = colors[Math.floor(Math.random() * colors.length)];
            var radius = 4 + Math.random() * 32;  // 4px ~ 36px
            return {
                x: Math.random() * W,
                y: startFromBottom ? H + radius + Math.random() * 100 : Math.random() * H,
                r: radius,
                speedY: 0.3 + Math.random() * 1.2,          // 상승 속도
                wobbleAmp: 0.3 + Math.random() * 1.5,        // 좌우 흔들림 크기
                wobbleSpeed: 0.005 + Math.random() * 0.015,   // 흔들림 속도
                wobbleOffset: Math.random() * Math.PI * 2,    // 흔들림 시작 위상
                opacity: 0.06 + Math.random() * 0.18,         // 투명도 (은은한)
                color: color,
                pulseSpeed: 0.003 + Math.random() * 0.008,    // 크기 맥박 속도
                pulseAmp: 0.08 + Math.random() * 0.12,        // 크기 맥박 정도
                t: Math.random() * 1000,                      // 시간 오프셋
            };
        }

        // 초기 물방울 배치 (화면 전체에 랜덤 분포)
        for (var i = 0; i < BUBBLE_COUNT; i++) {
            bubbles.push(createBubble(false));
        }

        function drawBubble(b) {
            var pulse = 1 + Math.sin(b.t * b.pulseSpeed) * b.pulseAmp;
            var r = b.r * pulse;
            var alpha = b.opacity;

            // 위로 갈수록 서서히 투명해짐
            var fadeRatio = b.y / H;
            alpha *= (0.3 + fadeRatio * 0.7);

            ctx.save();

            // 바깥 글로우
            var glow = ctx.createRadialGradient(b.x, b.y, r * 0.1, b.x, b.y, r * 1.8);
            glow.addColorStop(0, 'rgba(' + b.color.r + ',' + b.color.g + ',' + b.color.b + ',' + (alpha * 0.5) + ')');
            glow.addColorStop(1, 'rgba(' + b.color.r + ',' + b.color.g + ',' + b.color.b + ',0)');
            ctx.fillStyle = glow;
            ctx.beginPath();
            ctx.arc(b.x, b.y, r * 1.8, 0, Math.PI * 2);
            ctx.fill();

            // 메인 물방울 (반투명 원)
            var grad = ctx.createRadialGradient(b.x - r * 0.3, b.y - r * 0.3, r * 0.1, b.x, b.y, r);
            grad.addColorStop(0, 'rgba(' + b.color.r + ',' + b.color.g + ',' + b.color.b + ',' + (alpha * 1.2) + ')');
            grad.addColorStop(0.7, 'rgba(' + b.color.r + ',' + b.color.g + ',' + b.color.b + ',' + (alpha * 0.6) + ')');
            grad.addColorStop(1, 'rgba(' + b.color.r + ',' + b.color.g + ',' + b.color.b + ',' + (alpha * 0.1) + ')');
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.arc(b.x, b.y, r, 0, Math.PI * 2);
            ctx.fill();

            // 테두리 (물방울 느낌)
            ctx.strokeStyle = 'rgba(' + b.color.r + ',' + b.color.g + ',' + b.color.b + ',' + (alpha * 0.8) + ')';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.arc(b.x, b.y, r, 0, Math.PI * 2);
            ctx.stroke();

            // 하이라이트 (빛 반사)
            var hlX = b.x - r * 0.28;
            var hlY = b.y - r * 0.28;
            var hlR = r * 0.25;
            var hlGrad = ctx.createRadialGradient(hlX, hlY, 0, hlX, hlY, hlR);
            hlGrad.addColorStop(0, 'rgba(255,255,255,' + (alpha * 1.5) + ')');
            hlGrad.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.fillStyle = hlGrad;
            ctx.beginPath();
            ctx.arc(hlX, hlY, hlR, 0, Math.PI * 2);
            ctx.fill();

            ctx.restore();
        }

        function animate() {
            ctx.clearRect(0, 0, W, H);

            for (var i = 0; i < bubbles.length; i++) {
                var b = bubbles[i];
                
                // 상승
                b.y -= b.speedY;
                // 좌우 흔들림 (사인파)
                b.x += Math.sin(b.t * b.wobbleSpeed + b.wobbleOffset) * b.wobbleAmp;
                b.t++;

                // 화면 위로 사라지면 아래에서 새로 생성
                if (b.y < -b.r * 2) {
                    bubbles[i] = createBubble(true);
                }

                // 좌우 벗어나면 반대쪽으로
                if (b.x < -b.r) b.x = W + b.r;
                if (b.x > W + b.r) b.x = -b.r;

                drawBubble(b);
            }

            requestAnimationFrame(animate);
        }

        animate();
    })();

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
