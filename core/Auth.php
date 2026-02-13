<?php
/**
 * AltNET Ecount ERP - Authentication
 */
class Auth {
    
    public static function attempt($username, $password) {
        $db = Database::getInstance();
        $config = require __DIR__ . '/../config/app.php';
        
        $user = $db->fetch("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Opps ^^ 다시 시도해 보세요'];
        }
        
        // Check account lock
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['success' => false, 'message' => "계정이 잠겼습니다. {$remaining}분 후 다시 시도하세요."];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            $failCount = $user['login_fail_count'] + 1;
            $updateData = ['login_fail_count' => $failCount];
            
            if ($failCount >= $config['login_max_fail']) {
                $lockUntil = date('Y-m-d H:i:s', time() + $config['lock_duration']);
                $updateData['locked_until'] = $lockUntil;
                $updateData['login_fail_count'] = 0;
                $db->update('users', $updateData, 'id = ?', [$user['id']]);
                return ['success' => false, 'message' => '로그인 5회 실패. 계정이 5분간 잠겼습니다.'];
            }
            
            $db->update('users', $updateData, 'id = ?', [$user['id']]);
            $left = $config['login_max_fail'] - $failCount;
            return ['success' => false, 'message' => "Opps ^^ 다시 시도해 보세요 (남은 시도: {$left}회)"];
        }
        
        // Login success
        $db->update('users', [
            'login_fail_count' => 0, 
            'locked_until' => null, 
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);
        
        Session::set('user_id', $user['id']);
        Session::set('username', $user['username']);
        Session::set('user_name', $user['name']);
        Session::set('user_role', $user['role']);
        Session::set('last_activity', time());
        
        // Audit log
        AuditLog::log('LOGIN', null, null, null, null, '로그인 성공');
        
        return ['success' => true];
    }
    
    public static function logout() {
        AuditLog::log('LOGOUT', null, null, null, null, '로그아웃');
        Session::destroy();
    }
    
    public static function check() {
        return Session::isLoggedIn();
    }
    
    public static function requireLogin() {
        if (!self::check()) {
            redirect('?page=login');
        }
    }
    
    public static function requireRole($roles) {
        self::requireLogin();
        if (!is_array($roles)) $roles = [$roles];
        $userRole = Session::get('user_role');
        if (!in_array($userRole, $roles)) {
            http_response_code(403);
            echo '<div style="text-align:center;padding:50px;"><h2>접근 권한이 없습니다</h2><a href="?page=dashboard">대시보드로 돌아가기</a></div>';
            exit;
        }
    }
    
    public static function hasRole($roles) {
        if (!is_array($roles)) $roles = [$roles];
        return in_array(Session::get('user_role'), $roles);
    }

    public static function canEdit($recordUserId) {
        $role = Session::get('user_role');
        if (in_array($role, ['admin', 'manager'])) return true;
        return Session::getUserId() == $recordUserId;
    }
}
