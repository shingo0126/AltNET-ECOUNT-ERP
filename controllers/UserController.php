<?php
class UserController {
    
    public function index() {
        Auth::requireRole(['admin']);
        $db = Database::getInstance();
        $users = $db->fetchAll("SELECT * FROM users ORDER BY id");
        
        $pageTitle = '사용자 관리';
        ob_start();
        include __DIR__ . '/../views/users/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    public function save() {
        Auth::requireRole(['admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('?page=users');
        if (!CSRF::verify()) redirect('?page=users');
        
        $db = Database::getInstance();
        $id = (int)postParam('id');
        $username = trim(postParam('username', ''));
        $name = trim(postParam('name', ''));
        $email = trim(postParam('email', ''));
        $role = postParam('role', 'user');
        $password = postParam('password', '');
        
        if (empty($username) || empty($name)) {
            Session::set('flash_message', '사용자명과 이름을 입력하세요.');
            Session::set('flash_type', 'danger');
            redirect('?page=users');
        }
        
        if ($id > 0) {
            $data = ['username' => $username, 'name' => $name, 'email' => $email, 'role' => $role];
            if (!empty($password)) {
                $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
            }
            $old = $db->fetch("SELECT id, username, name, email, role FROM users WHERE id=?", [$id]);
            $db->update('users', $data, 'id=?', [$id]);
            AuditLog::log('UPDATE', 'users', $id, $old, $data);
        } else {
            if (empty($password)) {
                Session::set('flash_message', '비밀번호를 입력하세요.');
                Session::set('flash_type', 'danger');
                redirect('?page=users');
            }
            // Check duplicate
            $exists = $db->fetch("SELECT id FROM users WHERE username=?", [$username]);
            if ($exists) {
                Session::set('flash_message', '이미 존재하는 사용자명입니다.');
                Session::set('flash_type', 'danger');
                redirect('?page=users');
            }
            $id = $db->insert('users', [
                'username' => $username, 'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'name' => $name, 'email' => $email, 'role' => $role
            ]);
            AuditLog::log('INSERT', 'users', $id, null, ['username' => $username, 'name' => $name, 'role' => $role]);
        }
        
        Session::set('flash_message', '사용자가 저장되었습니다.');
        Session::set('flash_type', 'success');
        redirect('?page=users');
    }
    
    public function delete() {
        Auth::requireRole(['admin']);
        if (!CSRF::verify($_GET['token'] ?? '')) redirect('?page=users');
        
        $db = Database::getInstance();
        $id = (int)getParam('id');
        
        if ($id == Session::getUserId()) {
            Session::set('flash_message', '자기 자신은 삭제할 수 없습니다.');
            Session::set('flash_type', 'danger');
            redirect('?page=users');
        }
        
        $old = $db->fetch("SELECT id, username, name, role FROM users WHERE id=?", [$id]);
        if ($old) {
            $db->update('users', ['is_active' => 0], 'id=?', [$id]);
            AuditLog::log('DELETE', 'users', $id, $old, null, '사용자 비활성화');
        }
        redirect('?page=users');
    }
}
