<?php
/**
 * AltNET Ecount ERP - Session Manager
 */
class Session {
    private static $started = false;

    public static function start() {
        if (self::$started) return;
        
        $config = require __DIR__ . '/../config/app.php';
        
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', $config['session_timeout']);
        
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
