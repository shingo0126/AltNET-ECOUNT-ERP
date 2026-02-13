<?php
/**
 * AltNET Ecount ERP - Session Manager
 */
class Session {
    private static $started = false;

    /**
     * 경로가 파일시스템 디렉토리 경로인지 확인
     * tcp://, redis://, memcached:// 등 네트워크 URI는 false 반환
     */
    private static function isLocalPath($path) {
        if (empty($path)) return false;
        // 프로토콜(scheme)이 포함된 URI는 파일시스템 경로가 아님
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', $path)) return false;
        return true;
    }

    public static function start() {
        if (self::$started) return;
        
        $config = require __DIR__ . '/../config/app.php';
        
        // ★ 세션 핸들러 확인: files가 아니면(memcached, redis 등) 경로 검사 불필요
        $handler = ini_get('session.save_handler');
        
        // session.save_handler가 'files'인 경우에만 디렉토리 검사/폴백 수행
        if ($handler === 'files' || $handler === 'file' || empty($handler)) {
            $savePath = session_save_path();
            
            // ★ 네트워크 URI(tcp:// 등)가 설정되어 있으면 → 파일 기반으로 강제 전환
            if (!empty($savePath) && !self::isLocalPath($savePath)) {
                // tcp:// 같은 네트워크 경로가 save_path에 설정된 비정상 상태
                // → 파일 기반 세션으로 강제 전환
                ini_set('session.save_handler', 'files');
                $savePath = ''; // 아래 폴백 로직으로 진입
            }
            
            // 1단계: 기본 경로가 비어있거나 존재하지 않으면 폴백
            if (empty($savePath) || !@is_dir($savePath)) {
                $savePath = sys_get_temp_dir() . '/php_sessions';
                if (!@is_dir($savePath)) {
                    @mkdir($savePath, 0733, true);
                }
                session_save_path($savePath);
            }
            
            // 2단계: 쓰기 권한 확인, 불가하면 대체 경로 시도
            if (!@is_writable($savePath)) {
                $fallbackPaths = [
                    sys_get_temp_dir() . '/php_sessions_erp',
                    __DIR__ . '/../tmp/sessions',
                    '/tmp/php_sessions_erp',
                ];
                foreach ($fallbackPaths as $fallback) {
                    if (!@is_dir($fallback)) {
                        @mkdir($fallback, 0733, true);
                    }
                    if (@is_dir($fallback) && @is_writable($fallback)) {
                        session_save_path($fallback);
                        break;
                    }
                }
            }
        } else {
            // memcached, redis 등 네트워크 기반 세션 핸들러
            // → save_path는 tcp://... 형태이므로 파일시스템 검사 스킵
            $savePath = session_save_path();
            
            // 네트워크 핸들러가 설정되어 있지만 해당 확장이 로드되지 않은 경우
            // → files로 폴백하여 세션이 아예 작동하지 않는 상황 방지
            if (!extension_loaded($handler)) {
                ini_set('session.save_handler', 'files');
                $fallbackPath = sys_get_temp_dir() . '/php_sessions';
                if (!@is_dir($fallbackPath)) {
                    @mkdir($fallbackPath, 0733, true);
                }
                session_save_path($fallbackPath);
            }
        }
        
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.gc_maxlifetime', $config['session_timeout']);
        
        // 세션 쿠키 path를 '/'로 설정 (서브디렉토리 환경 대응)
        ini_set('session.cookie_path', '/');
        
        // session.cookie_samesite는 PHP 7.3+ 전용
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            ini_set('session.cookie_samesite', 'Lax');
        }
        
        session_start();
        self::$started = true;
        
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            if ($elapsed > $config['session_timeout']) {
                self::destroy();
                return;
            }
        }
        $_SESSION['last_activity'] = time();
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    public static function remove($key) {
        unset($_SESSION[$key]);
    }

    public static function destroy() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }
        session_destroy();
        self::$started = false;
    }

    public static function isLoggedIn() {
        return self::has('user_id');
    }

    public static function getUserId() {
        return self::get('user_id');
    }

    public static function getUser() {
        return [
            'id'       => self::get('user_id'),
            'username' => self::get('username'),
            'name'     => self::get('user_name'),
            'role'     => self::get('user_role'),
        ];
    }

    public static function getSessionInfo() {
        $config = require __DIR__ . '/../config/app.php';
        $lastActivity = self::get('last_activity', time());
        $elapsed = time() - $lastActivity;
        $remaining = max(0, $config['session_timeout'] - $elapsed);
        return [
            'remaining' => $remaining,
            'timeout'   => $config['session_timeout'],
            'warning'   => $config['session_warning'],
        ];
    }
}
