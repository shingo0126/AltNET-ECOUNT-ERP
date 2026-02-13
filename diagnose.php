<?php
/**
 * AltNET Ecount ERP - 로그인/세션 진단 스크립트 v2
 * 
 * 사용법: 브라우저에서 http://192.168.50.231:2026/diagnose.php 접속
 * 진단 완료 후 반드시 이 파일을 삭제하세요!
 * 
 * v2 수정사항:
 * - session.save_handler가 memcached/redis인 경우 대응
 * - session.save_path가 tcp:// URI인 경우 is_dir/file_exists 호출 방지
 * - PHP-FPM 환경에서 mod_rewrite 확인 방법 개선
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * 경로가 파일시스템 로컬 경로인지 확인
 * tcp://, redis://, memcached:// 등 네트워크 URI는 false 반환
 */
function isLocalFilePath($path) {
    if (empty($path)) return false;
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', $path)) return false;
    return true;
}

echo "<html><head><meta charset='UTF-8'><title>ERP 진단 v2</title>";
echo "<style>body{font-family:'Malgun Gothic',monospace;padding:20px;background:#1a1a1a;color:#e0e0e0;line-height:1.6;} ";
echo ".ok{color:#4caf50;font-weight:bold;} .fail{color:#f44336;font-weight:bold;} .warn{color:#ff9800;font-weight:bold;} .info{color:#90caf9;} ";
echo "h2{color:#64b5f6;border-bottom:1px solid #333;padding-bottom:5px;margin-top:30px;} ";
echo "pre{background:#2d2d2d;padding:10px;border-radius:5px;overflow-x:auto;} ";
echo "code{background:#2d2d2d;padding:2px 6px;border-radius:3px;color:#ffa726;} ";
echo ".box{background:#263238;border-left:4px solid #64b5f6;padding:10px 15px;margin:10px 0;border-radius:0 5px 5px 0;}</style></head><body>";
echo "<h1>AltNET Ecount ERP - 서버 환경 진단 v2</h1>";
echo "<p class='info'>진단 시각: " . date('Y-m-d H:i:s') . "</p>";

$allOk = true;
$critical = false;

// ============================================================
// 1. PHP 버전
// ============================================================
echo "<h2>1. PHP 버전</h2>";
$ver = PHP_VERSION;
$sapi = php_sapi_name();
echo "PHP Version: <b>$ver</b><br>";
echo "SAPI: <b>$sapi</b> ";
if (stripos($sapi, 'fpm') !== false) {
    echo "(PHP-FPM 모드)";
} elseif (stripos($sapi, 'apache') !== false || stripos($sapi, 'mod') !== false) {
    echo "(Apache mod_php 모드)";
} elseif (stripos($sapi, 'cgi') !== false) {
    echo "(CGI 모드)";
} else {
    echo "($sapi)";
}
echo "<br>";
if (version_compare($ver, '7.2', '>=')) {
    echo "<span class='ok'>✅ PHP 7.2 이상 - 호환됨</span><br>";
} else {
    echo "<span class='fail'>❌ PHP 7.2 미만 - 호환되지 않음</span><br>";
    $allOk = false;
}

// ============================================================
// 2. 필수 PHP 확장 모듈
// ============================================================
echo "<h2>2. PHP 확장 모듈</h2>";
$required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "<span class='ok'>✅ $ext</span><br>";
    } else {
        echo "<span class='fail'>❌ $ext - 설치 필요!</span><br>";
        $allOk = false;
    }
}
// 선택 확장
$optional = ['openssl', 'curl', 'memcached', 'redis'];
echo "<br>선택 확장:<br>";
foreach ($optional as $ext) {
    if (extension_loaded($ext)) {
        echo "<span class='info'>  ✓ $ext (설치됨)</span><br>";
    } else {
        echo "  - $ext (미설치)<br>";
    }
}

// ============================================================
// 3. Output Buffering
// ============================================================
echo "<h2>3. Output Buffering 설정</h2>";
$ob = ini_get('output_buffering');
echo "php.ini output_buffering = <b>" . ($ob ?: '0 (OFF)') . "</b><br>";
echo "현재 ob_get_level() = <b>" . ob_get_level() . "</b><br>";
if (!$ob || $ob === '0' || $ob === 'Off') {
    echo "<span class='warn'>⚠️ output_buffering이 OFF입니다.</span><br>";
    echo "→ 코드에서 ob_start()로 강제 활성화하도록 수정됨 (index.php)<br>";
} else {
    echo "<span class='ok'>✅ output_buffering이 ON입니다 ($ob bytes).</span><br>";
}

// ============================================================
// 4. 세션 설정 (★ tcp:// 대응)
// ============================================================
echo "<h2>4. 세션 설정</h2>";
$handler = ini_get('session.save_handler');
$savePath = ini_get('session.save_path') ?: session_save_path();

echo "session.save_handler = <b>$handler</b><br>";
echo "session.save_path = <b>" . ($savePath ?: '(비어있음)') . "</b><br>";
echo "session.cookie_httponly = <b>" . ini_get('session.cookie_httponly') . "</b><br>";
echo "session.use_only_cookies = <b>" . ini_get('session.use_only_cookies') . "</b><br>";
echo "session.cookie_path = <b>" . ini_get('session.cookie_path') . "</b><br>";
echo "<br>";

// ★ 핸들러별 분기 처리
if ($handler === 'files' || $handler === 'file' || empty($handler)) {
    // === 파일 기반 세션 ===
    echo "<span class='info'>▸ 세션 핸들러: 파일 기반 (files)</span><br>";
    
    if (!empty($savePath) && !isLocalFilePath($savePath)) {
        // save_handler=files인데 save_path가 tcp:// 인 비정상 상태
        echo "<span class='fail'>❌ 비정상 설정 감지!</span><br>";
        echo "→ save_handler='files'인데 save_path가 네트워크 URI: <code>$savePath</code><br>";
        echo "<div class='box'>";
        echo "<b>해결 방법 (택 1):</b><br>";
        echo "A) php.ini에서 save_path를 파일 경로로 변경:<br>";
        echo "  <code>session.save_path = \"/var/lib/php/sessions\"</code><br><br>";
        echo "B) 또는 memcached/redis를 사용하려면 핸들러도 함께 변경:<br>";
        echo "  <code>session.save_handler = memcached</code><br>";
        echo "  <code>session.save_path = \"$savePath\"</code><br><br>";
        echo "C) 코드에서 자동 폴백: Session.php가 이 상태를 감지하고<br>";
        echo "  자동으로 파일 기반으로 전환합니다 (v1.4.6에서 수정됨).<br>";
        echo "</div>";
        $allOk = false;
    } elseif (!empty($savePath) && isLocalFilePath($savePath)) {
        // 정상 파일 경로
        if (@is_dir($savePath)) {
            if (@is_writable($savePath)) {
                echo "<span class='ok'>✅ 세션 저장 디렉토리 존재 및 쓰기 가능</span><br>";
            } else {
                echo "<span class='fail'>❌ 세션 저장 디렉토리에 쓰기 권한 없음!</span><br>";
                echo "→ 해결: <code>chmod 733 $savePath</code> 또는 <code>chown apache:apache $savePath</code><br>";
                $allOk = false;
            }
        } else {
            echo "<span class='fail'>❌ 세션 저장 디렉토리가 존재하지 않음: $savePath</span><br>";
            echo "→ 해결: <code>mkdir -p $savePath && chmod 733 $savePath</code><br>";
            $allOk = false;
        }
    } else {
        // save_path 비어있음 - 시스템 기본값 사용
        echo "<span class='warn'>⚠️ session.save_path 비어있음 - 시스템 temp 사용</span><br>";
        $tmpDir = sys_get_temp_dir();
        echo "sys_get_temp_dir() = <b>$tmpDir</b><br>";
        if (@is_writable($tmpDir)) {
            echo "<span class='ok'>✅ 임시 디렉토리 쓰기 가능</span><br>";
        } else {
            echo "<span class='fail'>❌ 임시 디렉토리 쓰기 불가!</span><br>";
            $allOk = false;
        }
    }
} else {
    // === 네트워크 기반 세션 (memcached, redis 등) ===
    echo "<span class='info'>▸ 세션 핸들러: 네트워크 기반 ($handler)</span><br>";
    echo "save_path (서버 주소): <b>$savePath</b><br>";
    
    // 해당 확장 모듈 로드 확인
    if (extension_loaded($handler)) {
        echo "<span class='ok'>✅ $handler 확장 모듈 로드됨</span><br>";
        
        // 연결 테스트 (memcached)
        if ($handler === 'memcached' && class_exists('Memcached')) {
            $mc = new Memcached();
            // tcp://host:port 형식에서 host:port 추출
            $cleanPath = preg_replace('#^tcp://#', '', $savePath);
            $parts = explode(':', $cleanPath);
            $host = $parts[0] ?? '127.0.0.1';
            $port = intval($parts[1] ?? 11211);
            $mc->addServer($host, $port);
            $mc->set('erp_diag_test', 'ok', 5);
            if ($mc->get('erp_diag_test') === 'ok') {
                echo "<span class='ok'>✅ Memcached 서버 연결 성공 ($host:$port)</span><br>";
            } else {
                echo "<span class='fail'>❌ Memcached 서버 연결 실패! ($host:$port)</span><br>";
                echo "→ Memcached 서비스가 실행 중인지 확인: <code>systemctl status memcached</code><br>";
                $allOk = false;
            }
        } elseif ($handler === 'redis' && class_exists('Redis')) {
            try {
                $redis = new Redis();
                $cleanPath = preg_replace('#^tcp://#', '', $savePath);
                $parts = explode(':', $cleanPath);
                $host = $parts[0] ?? '127.0.0.1';
                $port = intval($parts[1] ?? 6379);
                $redis->connect($host, $port, 2);
                echo "<span class='ok'>✅ Redis 서버 연결 성공 ($host:$port)</span><br>";
            } catch (Exception $e) {
                echo "<span class='fail'>❌ Redis 서버 연결 실패!</span><br>";
                echo "→ Redis 서비스 확인: <code>systemctl status redis</code><br>";
                $allOk = false;
            }
        }
    } else {
        echo "<span class='fail'>❌ $handler 확장 모듈이 로드되지 않음!</span><br>";
        echo "<div class='box'>";
        echo "<b>해결 방법 (택 1):</b><br>";
        echo "A) $handler 확장 설치: <code>yum install php-$handler</code> 또는 <code>apt install php-$handler</code><br>";
        echo "B) 파일 기반 세션으로 전환 (php.ini):<br>";
        echo "  <code>session.save_handler = files</code><br>";
        echo "  <code>session.save_path = \"/var/lib/php/sessions\"</code><br><br>";
        echo "C) 코드에서 자동 폴백: Session.php가 확장 미설치를 감지하고<br>";
        echo "  자동으로 파일 기반으로 전환합니다 (v1.4.6에서 수정됨).<br>";
        echo "</div>";
        $allOk = false;
        $critical = true;
    }
}

// ============================================================
// 5. 세션 실제 동작 테스트 (★ 핸들러 무관하게 테스트)
// ============================================================
echo "<h2>5. 세션 실제 동작 테스트</h2>";
try {
    // 기존 세션이 있으면 닫기
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    // ★ Session.php의 폴백 로직을 먼저 로드하여 세션 시작
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Session.php';
    require_once __DIR__ . '/core/CSRF.php';
    require_once __DIR__ . '/core/Auth.php';
    require_once __DIR__ . '/core/AuditLog.php';
    require_once __DIR__ . '/core/Helper.php';
    
    Session::start();
    
    $testKey = 'diagnose_test_' . time();
    $_SESSION[$testKey] = 'test_value';
    $sessionId = session_id();
    
    // 세션 저장 후 닫기
    session_write_close();
    
    echo "세션 ID: <b>$sessionId</b><br>";
    echo "실제 사용된 session.save_handler: <b>" . ini_get('session.save_handler') . "</b><br>";
    echo "실제 사용된 session.save_path: <b>" . session_save_path() . "</b><br>";
    
    // 파일 기반인 경우만 파일 존재 확인
    $actualHandler = ini_get('session.save_handler');
    $actualPath = session_save_path();
    if (($actualHandler === 'files' || empty($actualHandler)) && isLocalFilePath($actualPath)) {
        $sessionFile = $actualPath . '/sess_' . $sessionId;
        if (@file_exists($sessionFile)) {
            echo "<span class='ok'>✅ 세션 파일 생성됨: $sessionFile</span><br>";
            $size = @filesize($sessionFile);
            echo "파일 크기: <b>$size bytes</b><br>";
            $contents = @file_get_contents($sessionFile);
            if ($contents && strpos($contents, $testKey) !== false) {
                echo "<span class='ok'>✅ 세션 데이터 정상 기록됨</span><br>";
            } else {
                echo "<span class='fail'>❌ 세션 데이터가 파일에 기록되지 않음!</span><br>";
                $allOk = false;
            }
        } else {
            echo "<span class='fail'>❌ 세션 파일이 생성되지 않음!</span><br>";
            echo "예상 경로: $sessionFile<br>";
            $allOk = false;
        }
    } else {
        echo "<span class='info'>▸ 네트워크 기반 세션 - 파일 확인 생략, 데이터 읽기로 검증</span><br>";
    }
    
    // ★ 핵심 테스트: 세션 다시 열어서 데이터가 유지되는지 확인
    session_start();
    if (isset($_SESSION[$testKey]) && $_SESSION[$testKey] === 'test_value') {
        echo "<span class='ok'>✅ 세션 데이터 재읽기 성공 (세션 유지 확인)</span><br>";
    } else {
        echo "<span class='fail'>❌ 세션 데이터 재읽기 실패! (로그인 후 세션 유실 원인)</span><br>";
        $critical = true;
        $allOk = false;
    }
    unset($_SESSION[$testKey]);
    session_write_close();
    
} catch (Exception $e) {
    echo "<span class='fail'>❌ 세션 테스트 실패: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    $allOk = false;
    $critical = true;
} catch (Error $e) {
    echo "<span class='fail'>❌ 세션 테스트 치명적 오류: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    $allOk = false;
    $critical = true;
}

// ============================================================
// 6. 데이터베이스 연결
// ============================================================
echo "<h2>6. 데이터베이스 연결</h2>";
try {
    $dbConfig = require __DIR__ . '/config/database.php';
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    echo "<span class='ok'>✅ 데이터베이스 연결 성공</span><br>";
    echo "호스트: <b>{$dbConfig['host']}:{$dbConfig['port']}</b>, DB: <b>{$dbConfig['dbname']}</b><br>";
    
    // users 테이블 확인
    $stmt = $pdo->query("SELECT id, username, name, role FROM users WHERE username = 'altnet'");
    $user = $stmt->fetch();
    if ($user) {
        echo "<span class='ok'>✅ altnet 사용자 존재: {$user['name']} ({$user['role']})</span><br>";
        
        // 비밀번호 확인
        $stmt2 = $pdo->query("SELECT password_hash FROM users WHERE username = 'altnet'");
        $hash = $stmt2->fetch()['password_hash'];
        if (password_verify('altnet2016!', $hash)) {
            echo "<span class='ok'>✅ 비밀번호 검증 통과 (altnet2016!)</span><br>";
        } else {
            echo "<span class='fail'>❌ 비밀번호 불일치!</span><br>";
            $allOk = false;
        }
    } else {
        echo "<span class='fail'>❌ altnet 사용자가 없음! seed.sql 실행 필요</span><br>";
        $allOk = false;
    }
    
    // tax_invoices 테이블 확인
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM tax_invoices");
        $cnt = $stmt->fetch();
        echo "<span class='ok'>✅ tax_invoices 테이블 존재 (행: {$cnt['cnt']})</span><br>";
    } catch (PDOException $e) {
        echo "<span class='fail'>❌ tax_invoices 테이블 없음! → schema.sql 실행 필요</span><br>";
        $allOk = false;
    }
    
    // pending_reason 컬럼 확인
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tax_invoices LIKE 'pending_reason'");
        if ($stmt->fetch()) {
            echo "<span class='ok'>✅ pending_reason 컬럼 존재</span><br>";
        } else {
            echo "<span class='fail'>❌ pending_reason 컬럼 없음!</span><br>";
            echo "→ <code>ALTER TABLE tax_invoices ADD COLUMN pending_reason TEXT DEFAULT NULL AFTER status;</code><br>";
            $allOk = false;
        }
    } catch (PDOException $e) {
        echo "<span class='warn'>⚠️ 컬럼 확인 실패: " . $e->getMessage() . "</span><br>";
    }
    
} catch (PDOException $e) {
    echo "<span class='fail'>❌ 데이터베이스 연결 실패: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "→ config/database.php의 접속 정보를 확인하세요.<br>";
    $allOk = false;
    $critical = true;
}

// ============================================================
// 7. Apache/서버 설정 (★ FPM 환경 대응)
// ============================================================
echo "<h2>7. 서버 환경</h2>";
echo "SERVER_SOFTWARE: <b>" . htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</b><br>";
echo "DOCUMENT_ROOT: <b>" . htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</b><br>";
echo "SCRIPT_FILENAME: <b>" . htmlspecialchars($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "</b><br>";
echo "REQUEST_URI: <b>" . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') . "</b><br>";

// .htaccess 확인
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "<span class='ok'>✅ .htaccess 파일 존재</span><br>";
} else {
    echo "<span class='warn'>⚠️ .htaccess 파일 없음</span><br>";
}

// ★ mod_rewrite 확인 - 환경에 따라 다른 방법 사용
echo "<br><b>mod_rewrite 확인:</b><br>";
if (function_exists('apache_get_modules')) {
    // mod_php 환경
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<span class='ok'>✅ mod_rewrite 활성화됨 (mod_php에서 직접 확인)</span><br>";
    } else {
        echo "<span class='fail'>❌ mod_rewrite 비활성화!</span><br>";
        echo "→ 해결: <code>a2enmod rewrite && systemctl restart apache2</code><br>";
        $allOk = false;
    }
} else {
    // PHP-FPM 또는 CGI 환경 - apache_get_modules() 사용 불가
    echo "<span class='info'>▸ PHP-FPM/CGI 모드 - apache_get_modules() 사용 불가</span><br>";
    echo "→ mod_rewrite 간접 확인 방법:<br>";
    
    // 방법 1: 현재 URL이 .php 없이 접근됐는지 확인
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, 'diagnose.php') !== false) {
        echo "  현재 URL에 .php가 포함됨 → RewriteRule이 이 파일을 처리하지 않음 (정상)<br>";
    }
    
    // 방법 2: shell 명령으로 확인
    $apachectl = @shell_exec('apachectl -M 2>&1');
    if ($apachectl && stripos($apachectl, 'rewrite') !== false) {
        echo "<span class='ok'>✅ mod_rewrite 활성화 확인 (apachectl -M)</span><br>";
    } elseif ($apachectl) {
        echo "<span class='fail'>❌ mod_rewrite가 로드되지 않음!</span><br>";
        echo "→ 해결: CentOS: <code>yum install mod_rewrite</code><br>";
        echo "→ Ubuntu: <code>a2enmod rewrite && systemctl restart apache2</code><br>";
        $allOk = false;
    } else {
        // httpd -M도 시도
        $httpd = @shell_exec('httpd -M 2>&1');
        if ($httpd && stripos($httpd, 'rewrite') !== false) {
            echo "<span class='ok'>✅ mod_rewrite 활성화 확인 (httpd -M)</span><br>";
        } elseif ($httpd) {
            echo "<span class='fail'>❌ mod_rewrite가 로드되지 않음!</span><br>";
            $allOk = false;
        } else {
            echo "<span class='warn'>⚠️ mod_rewrite 자동 확인 불가 - 수동 확인 필요</span><br>";
            echo "  서버에서 다음 명령으로 확인: <code>httpd -M | grep rewrite</code><br>";
        }
    }
    
    // AllowOverride 확인 안내
    echo "<br>AllowOverride 설정 확인 필요:<br>";
    echo "  Apache 설정 파일에서 프로젝트 디렉토리에 <code>AllowOverride All</code>이 설정되어야 합니다.<br>";
    echo "  <code>/etc/httpd/conf/httpd.conf</code> 또는 <code>/etc/apache2/sites-enabled/</code> 확인<br>";
}

// ============================================================
// 8. SELinux 확인
// ============================================================
echo "<h2>8. SELinux 상태</h2>";
if (PHP_OS_FAMILY === 'Linux') {
    $selinux = @shell_exec('getenforce 2>&1');
    if ($selinux) {
        $status = trim($selinux);
        echo "SELinux 상태: <b>$status</b><br>";
        if ($status === 'Enforcing') {
            echo "<span class='warn'>⚠️ SELinux가 Enforcing 모드입니다.</span><br>";
            echo "<div class='box'>";
            echo "SELinux가 httpd의 세션 파일 쓰기/네트워크 접근을 차단할 수 있습니다.<br>";
            echo "임시 비활성화: <code>setenforce 0</code><br>";
            echo "영구 비활성화: <code>/etc/selinux/config</code>에서 SELINUX=disabled<br>";
            echo "httpd 네트워크 허용: <code>setsebool -P httpd_can_network_connect 1</code><br>";
            echo "</div>";
        } elseif ($status === 'Permissive') {
            echo "<span class='ok'>✅ SELinux Permissive 모드 (경고만, 차단 안 함)</span><br>";
        } else {
            echo "<span class='ok'>✅ SELinux 비활성화됨</span><br>";
        }
    } else {
        echo "SELinux 확인 불가 (설치되지 않았거나 권한 없음)<br>";
    }
} else {
    echo "Linux가 아닌 환경 - 해당 없음<br>";
}

// ============================================================
// 9. 로그인 시뮬레이션
// ============================================================
echo "<h2>9. 로그인 코드 흐름 시뮬레이션</h2>";
try {
    // Session.php가 이미 로드됨 (섹션 5에서)
    Session::start();
    echo "Session::start() 호출 완료<br>";
    echo "세션 ID: <b>" . session_id() . "</b><br>";
    echo "실제 handler: <b>" . ini_get('session.save_handler') . "</b><br>";
    echo "실제 save_path: <b>" . session_save_path() . "</b><br>";
    
    // Auth::attempt 시뮬레이션
    $result = Auth::attempt('altnet', 'altnet2016!');
    echo "Auth::attempt() 결과: <b>" . ($result['success'] ? '성공 ✅' : '실패 ❌ - ' . ($result['message'] ?? '')) . "</b><br>";
    
    if ($result['success']) {
        echo "Session user_id: <b>" . Session::get('user_id') . "</b><br>";
        echo "Session username: <b>" . Session::get('username') . "</b><br>";
        echo "Session user_role: <b>" . Session::get('user_role') . "</b><br>";
        echo "Auth::check(): <b>" . (Auth::check() ? 'true ✅' : 'false ❌') . "</b><br>";
        
        // session_write_close 테스트
        session_write_close();
        echo "<span class='ok'>✅ session_write_close() 성공</span><br>";
        
        // 세션 다시 읽기 (★ 이것이 로그인 후 리다이렉트의 핵심 테스트)
        session_start();
        echo "세션 재시작 후 user_id: <b>" . ($_SESSION['user_id'] ?? 'NULL') . "</b><br>";
        if (!empty($_SESSION['user_id'])) {
            echo "<span class='ok'>✅ 세션 유지 확인됨 - 리다이렉트 후에도 로그인 유지됨</span><br>";
        } else {
            echo "<span class='fail'>❌ 세션 유지 실패! - 리다이렉트 후 로그인 상태가 사라짐</span><br>";
            $allOk = false;
            $critical = true;
        }
        
        // 정리
        Auth::logout();
    }
    
} catch (Exception $e) {
    echo "<span class='fail'>❌ 오류: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    $allOk = false;
} catch (Error $e) {
    echo "<span class='fail'>❌ 치명적 오류: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    $allOk = false;
}

// ============================================================
// 10. 파일 권한 확인
// ============================================================
echo "<h2>10. 중요 파일/디렉토리 권한</h2>";
$paths = [
    __DIR__ => '프로젝트 루트',
    __DIR__ . '/logs' => '로그 디렉토리',
    __DIR__ . '/config' => '설정 디렉토리',
    __DIR__ . '/index.php' => '메인 엔트리',
    __DIR__ . '/tmp' => 'tmp 디렉토리 (세션 폴백)',
];
foreach ($paths as $path => $desc) {
    if (@file_exists($path)) {
        $perms = substr(sprintf('%o', @fileperms($path)), -4);
        if (function_exists('posix_getpwuid')) {
            $ownerInfo = @posix_getpwuid(@fileowner($path));
            $groupInfo = @posix_getgrgid(@filegroup($path));
            $owner = $ownerInfo['name'] ?? @fileowner($path);
            $group = $groupInfo['name'] ?? @filegroup($path);
        } else {
            $owner = @fileowner($path);
            $group = @filegroup($path);
        }
        echo "$desc: <b>$perms</b> ($owner:$group) - $path<br>";
    } else {
        echo "$desc: <span class='warn'>미존재</span> - $path<br>";
    }
}

// logs 디렉토리 확인
$logDir = __DIR__ . '/logs';
if (!@is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
if (@is_writable($logDir)) {
    echo "<span class='ok'>✅ logs 디렉토리 쓰기 가능</span><br>";
} else {
    echo "<span class='warn'>⚠️ logs 디렉토리 쓰기 불가 (에러 로깅 불가)</span><br>";
    echo "→ 해결: <code>chmod 755 $logDir && chown apache:apache $logDir</code><br>";
}

// ============================================================
// 최종 결과
// ============================================================
echo "<h2>===== 최종 진단 결과 =====</h2>";
if ($allOk) {
    echo "<span class='ok' style='font-size:18px;'>✅ 모든 검사 통과! 로그인/세션이 정상 작동합니다.</span><br><br>";
    echo "만약 여전히 로그인 후 이동이 안 된다면:<br>";
    echo "1. 브라우저 캐시/쿠키를 <b>완전히</b> 삭제한 후 재시도<br>";
    echo "2. 시크릿/인코그니토 모드에서 시도<br>";
    echo "3. F12 → Network 탭에서 POST /login 요청의 Response Headers 확인<br>";
    echo "   - <code>Set-Cookie: PHPSESSID=...</code> 헤더가 있는지 확인<br>";
    echo "   - <code>Location: ?page=dashboard</code> 헤더가 있는지 확인<br>";
} elseif ($critical) {
    echo "<span class='fail' style='font-size:18px;'>🚨 치명적 문제 발견! 위의 빨간색 항목을 반드시 수정하세요.</span><br>";
    echo "<div class='box'>";
    echo "가장 빠른 해결 방법:<br>";
    echo "1. php.ini에서 세션 설정 확인/수정:<br>";
    echo "  <code>session.save_handler = files</code><br>";
    echo "  <code>session.save_path = \"/var/lib/php/sessions\"</code><br>";
    echo "2. 디렉토리 생성/권한 부여:<br>";
    echo "  <code>mkdir -p /var/lib/php/sessions && chmod 733 /var/lib/php/sessions</code><br>";
    echo "3. Apache/PHP-FPM 재시작:<br>";
    echo "  <code>systemctl restart php-fpm && systemctl restart httpd</code><br>";
    echo "</div>";
} else {
    echo "<span class='warn' style='font-size:18px;'>⚠️ 일부 문제가 발견되었습니다. 위의 주황/빨간색 항목을 확인하세요.</span><br>";
}

echo "<br><br><span class='warn'>⚠️ 보안 주의: 진단 완료 후 이 파일(diagnose.php)을 반드시 삭제하세요!</span>";
echo "</body></html>";
