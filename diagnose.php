<?php
/**
 * AltNET Ecount ERP - 로그인/세션 진단 스크립트
 * 
 * 사용법: 브라우저에서 http://192.168.50.231:2026/diagnose.php 접속
 * 진단 완료 후 반드시 이 파일을 삭제하세요!
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><meta charset='UTF-8'><title>ERP 진단</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1a1a1a;color:#e0e0e0;} ";
echo ".ok{color:#4caf50;font-weight:bold;} .fail{color:#f44336;font-weight:bold;} .warn{color:#ff9800;font-weight:bold;} ";
echo "h2{color:#64b5f6;border-bottom:1px solid #333;padding-bottom:5px;} pre{background:#2d2d2d;padding:10px;border-radius:5px;overflow-x:auto;}</style></head><body>";
echo "<h1>AltNET Ecount ERP - 서버 환경 진단</h1>";

$allOk = true;

// 1. PHP 버전
echo "<h2>1. PHP 버전</h2>";
$ver = PHP_VERSION;
echo "PHP Version: <b>$ver</b><br>";
if (version_compare($ver, '7.2', '>=')) {
    echo "<span class='ok'>✅ PHP 7.2 이상 - 호환됨</span><br>";
} else {
    echo "<span class='fail'>❌ PHP 7.2 미만 - 호환되지 않음</span><br>";
    $allOk = false;
}

// 2. 필수 PHP 확장 모듈
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

// 3. Output Buffering
echo "<h2>3. Output Buffering 설정</h2>";
$ob = ini_get('output_buffering');
echo "php.ini output_buffering = <b>" . ($ob ?: '0 (OFF)') . "</b><br>";
echo "현재 ob_get_level() = <b>" . ob_get_level() . "</b><br>";
if (!$ob || $ob === '0' || $ob === 'Off') {
    echo "<span class='warn'>⚠️ output_buffering이 OFF입니다.</span><br>";
    echo "→ 코드에서 ob_start()로 강제 활성화하도록 수정됨 (index.php 라인 9)<br>";
} else {
    echo "<span class='ok'>✅ output_buffering이 ON입니다.</span><br>";
}

// 4. 세션 설정
echo "<h2>4. 세션 설정</h2>";
$savePath = ini_get('session.save_path') ?: session_save_path();
echo "session.save_path = <b>" . ($savePath ?: '(비어있음 - 시스템 기본값)') . "</b><br>";
echo "session.save_handler = <b>" . ini_get('session.save_handler') . "</b><br>";
echo "session.cookie_httponly = <b>" . ini_get('session.cookie_httponly') . "</b><br>";
echo "session.use_only_cookies = <b>" . ini_get('session.use_only_cookies') . "</b><br>";
echo "session.cookie_path = <b>" . ini_get('session.cookie_path') . "</b><br>";

if (!empty($savePath)) {
    if (is_dir($savePath)) {
        if (is_writable($savePath)) {
            echo "<span class='ok'>✅ 세션 저장 디렉토리 쓰기 가능</span><br>";
        } else {
            echo "<span class='fail'>❌ 세션 저장 디렉토리에 쓰기 권한 없음!</span><br>";
            echo "→ 해결: <code>chmod 733 $savePath</code> 또는 <code>chown apache:apache $savePath</code><br>";
            $allOk = false;
        }
    } else {
        echo "<span class='fail'>❌ 세션 저장 디렉토리가 존재하지 않음!</span><br>";
        echo "→ 해결: <code>mkdir -p $savePath && chmod 733 $savePath</code><br>";
        $allOk = false;
    }
} else {
    echo "<span class='warn'>⚠️ session.save_path가 비어있음 - 시스템 temp 사용</span><br>";
    $tmpDir = sys_get_temp_dir();
    echo "sys_get_temp_dir() = <b>$tmpDir</b><br>";
    if (is_writable($tmpDir)) {
        echo "<span class='ok'>✅ 임시 디렉토리 쓰기 가능</span><br>";
    } else {
        echo "<span class='fail'>❌ 임시 디렉토리에 쓰기 권한 없음!</span><br>";
        $allOk = false;
    }
}

// 5. 세션 실제 동작 테스트
echo "<h2>5. 세션 실제 동작 테스트</h2>";
try {
    // 기존 세션이 있으면 닫기
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    // 새 세션 시작
    session_start();
    $testKey = 'diagnose_test_' . time();
    $_SESSION[$testKey] = 'test_value';
    $sessionId = session_id();
    session_write_close();
    
    echo "세션 ID: <b>$sessionId</b><br>";
    
    // 세션 파일 확인
    $currentSavePath = session_save_path() ?: ini_get('session.save_path');
    if (!empty($currentSavePath)) {
        $sessionFile = $currentSavePath . '/sess_' . $sessionId;
        if (file_exists($sessionFile)) {
            echo "<span class='ok'>✅ 세션 파일 생성됨: $sessionFile</span><br>";
            echo "파일 크기: <b>" . filesize($sessionFile) . " bytes</b><br>";
            $contents = file_get_contents($sessionFile);
            if (strpos($contents, $testKey) !== false) {
                echo "<span class='ok'>✅ 세션 데이터 정상 기록됨</span><br>";
            } else {
                echo "<span class='fail'>❌ 세션 데이터가 파일에 기록되지 않음!</span><br>";
                $allOk = false;
            }
        } else {
            echo "<span class='fail'>❌ 세션 파일이 생성되지 않음!</span><br>";
            echo "경로: $sessionFile<br>";
            $allOk = false;
        }
    }
    
    // 세션 다시 읽기 테스트
    session_start();
    if (isset($_SESSION[$testKey]) && $_SESSION[$testKey] === 'test_value') {
        echo "<span class='ok'>✅ 세션 데이터 다시 읽기 성공</span><br>";
    } else {
        echo "<span class='fail'>❌ 세션 데이터 다시 읽기 실패! (세션 유지 불가)</span><br>";
        $allOk = false;
    }
    unset($_SESSION[$testKey]);
    session_write_close();
    
} catch (Exception $e) {
    echo "<span class='fail'>❌ 세션 테스트 실패: " . $e->getMessage() . "</span><br>";
    $allOk = false;
}

// 6. 데이터베이스 연결
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
    } else {
        echo "<span class='fail'>❌ altnet 사용자가 없음!</span><br>";
        $allOk = false;
    }
    
    // tax_invoices 테이블 확인 (대시보드에서 사용)
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
        $stmt = $pdo->query("SELECT pending_reason FROM tax_invoices LIMIT 1");
        echo "<span class='ok'>✅ pending_reason 컬럼 존재</span><br>";
    } catch (PDOException $e) {
        echo "<span class='fail'>❌ pending_reason 컬럼 없음! → ALTER TABLE 실행 필요</span><br>";
        echo "→ <code>ALTER TABLE tax_invoices ADD COLUMN pending_reason TEXT DEFAULT NULL AFTER status;</code><br>";
        $allOk = false;
    }
    
} catch (PDOException $e) {
    echo "<span class='fail'>❌ 데이터베이스 연결 실패: " . $e->getMessage() . "</span><br>";
    echo "→ config/database.php의 접속 정보를 확인하세요.<br>";
    $allOk = false;
}

// 7. Apache/서버 설정
echo "<h2>7. 서버 환경</h2>";
echo "SERVER_SOFTWARE: <b>" . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</b><br>";
echo "DOCUMENT_ROOT: <b>" . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</b><br>";
echo "SCRIPT_FILENAME: <b>" . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "</b><br>";
echo "REQUEST_URI: <b>" . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</b><br>";

// .htaccess 확인
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "<span class='ok'>✅ .htaccess 파일 존재</span><br>";
} else {
    echo "<span class='warn'>⚠️ .htaccess 파일 없음</span><br>";
}

// mod_rewrite 확인
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<span class='ok'>✅ mod_rewrite 활성화됨</span><br>";
    } else {
        echo "<span class='fail'>❌ mod_rewrite 비활성화! Apache 설정 필요</span><br>";
        $allOk = false;
    }
} else {
    echo "<span class='warn'>⚠️ apache_get_modules() 사용 불가 (CGI/FPM 모드)</span><br>";
    echo "→ Apache 설정에서 mod_rewrite 활성화를 확인하세요.<br>";
}

// 8. SELinux 확인 (Linux 환경)
echo "<h2>8. SELinux 상태 (Linux)</h2>";
if (PHP_OS_FAMILY === 'Linux') {
    $selinux = @shell_exec('getenforce 2>&1');
    if ($selinux) {
        $status = trim($selinux);
        echo "SELinux 상태: <b>$status</b><br>";
        if ($status === 'Enforcing') {
            echo "<span class='warn'>⚠️ SELinux가 활성화되어 있습니다.</span><br>";
            echo "→ httpd가 세션 파일 쓰기/네트워크 접근을 차단할 수 있습니다.<br>";
            echo "→ 임시 비활성화: <code>setenforce 0</code><br>";
            echo "→ 영구 설정: <code>setsebool -P httpd_can_network_connect 1</code><br>";
        } else {
            echo "<span class='ok'>✅ SELinux가 비활성화/허용 모드</span><br>";
        }
    } else {
        echo "SELinux 확인 불가 (설치되지 않음 또는 권한 없음)<br>";
    }
} else {
    echo "Linux가 아닌 환경 - 해당 없음<br>";
}

// 9. 로그인 시뮬레이션 (실제 POST 없이 코드 흐름만 확인)
echo "<h2>9. 로그인 코드 흐름 시뮬레이션</h2>";
try {
    require_once __DIR__ . '/core/Database.php';
    require_once __DIR__ . '/core/Session.php';
    require_once __DIR__ . '/core/Auth.php';
    require_once __DIR__ . '/core/AuditLog.php';
    require_once __DIR__ . '/core/Helper.php';
    require_once __DIR__ . '/core/CSRF.php';
    
    // Session 시작
    Session::start();
    echo "Session::start() 호출 완료<br>";
    echo "세션 ID: <b>" . session_id() . "</b><br>";
    
    // Auth::attempt 시뮬레이션
    $result = Auth::attempt('altnet', 'altnet2016!');
    echo "Auth::attempt() 결과: <b>" . ($result['success'] ? '성공' : '실패 - ' . $result['message']) . "</b><br>";
    
    if ($result['success']) {
        echo "Session user_id: <b>" . Session::get('user_id') . "</b><br>";
        echo "Session username: <b>" . Session::get('username') . "</b><br>";
        echo "Session user_role: <b>" . Session::get('user_role') . "</b><br>";
        echo "Auth::check(): <b>" . (Auth::check() ? 'true' : 'false') . "</b><br>";
        
        // session_write_close 테스트
        session_write_close();
        echo "<span class='ok'>✅ session_write_close() 성공</span><br>";
        
        // 세션 다시 읽기
        session_start();
        echo "세션 재시작 후 user_id: <b>" . ($_SESSION['user_id'] ?? 'NULL') . "</b><br>";
        if (!empty($_SESSION['user_id'])) {
            echo "<span class='ok'>✅ 세션 유지 확인됨 - 리다이렉트 후 로그인 유지됨</span><br>";
        } else {
            echo "<span class='fail'>❌ 세션 유지 실패! - 리다이렉트 후 로그인 상태 유실됨</span><br>";
            $allOk = false;
        }
    }
    
    // 정리
    Auth::logout();
    
} catch (Exception $e) {
    echo "<span class='fail'>❌ 오류: " . $e->getMessage() . "</span><br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    $allOk = false;
}

// 10. 파일 권한 확인
echo "<h2>10. 중요 파일/디렉토리 권한</h2>";
$paths = [
    __DIR__ => '프로젝트 루트',
    __DIR__ . '/logs' => '로그 디렉토리',
    __DIR__ . '/config' => '설정 디렉토리',
    __DIR__ . '/index.php' => '메인 엔트리',
];
foreach ($paths as $path => $desc) {
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($path))['name'] ?? fileowner($path) : fileowner($path);
        $group = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($path))['name'] ?? filegroup($path) : filegroup($path);
        echo "$desc: <b>$perms</b> ($owner:$group) - $path<br>";
    } else {
        echo "<span class='warn'>⚠️ $desc 없음: $path</span><br>";
    }
}

// logs 디렉토리 쓰기 테스트
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
if (is_writable($logDir)) {
    echo "<span class='ok'>✅ logs 디렉토리 쓰기 가능</span><br>";
} else {
    echo "<span class='warn'>⚠️ logs 디렉토리 쓰기 불가 (에러 로깅 불가)</span><br>";
}

// 최종 결과
echo "<h2>===== 최종 진단 결과 =====</h2>";
if ($allOk) {
    echo "<span class='ok' style='font-size:18px;'>✅ 모든 검사 통과! 로그인/세션이 정상 작동해야 합니다.</span><br><br>";
    echo "만약 여전히 로그인 후 이동이 안 된다면:<br>";
    echo "1. 브라우저 캐시/쿠키를 삭제한 후 재시도<br>";
    echo "2. 시크릿/인코그니토 모드에서 시도<br>";
    echo "3. F12 → Network 탭에서 POST 요청의 Response Headers 확인<br>";
    echo "   - Set-Cookie 헤더가 있는지 확인<br>";
    echo "   - Location 헤더가 있는지 확인<br>";
} else {
    echo "<span class='fail' style='font-size:18px;'>❌ 문제가 발견되었습니다. 위의 빨간색 항목을 수정하세요.</span><br>";
}

echo "<br><br><span class='warn'>⚠️ 보안 주의: 진단 완료 후 이 파일(diagnose.php)을 반드시 삭제하세요!</span>";
echo "</body></html>";
