<?php
class AuthController {
    
    public function index() {
        // If already logged in, go to dashboard
        if (Auth::check()) {
            redirect('?page=dashboard');
        }
        
        $error = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim(postParam('username', ''));
            $password = postParam('password', '');
            
            // Check empty fields
            if (empty($username) || empty($password)) {
                $error = 'Opps ^^ 빈칸이 있네요';
            } else {
                $result = Auth::attempt($username, $password);
                if ($result['success']) {
                    // ★ 세션 ID를 재생성하여 세션 고정 공격 방지 + 새 세션 쿠키 발급 보장
                    session_regenerate_id(true);
                    redirect('?page=dashboard');
                } else {
                    $error = $result['message'];
                }
            }
        }
        
        // ★ Content-Type은 실제 HTML 렌더링 직전에만 설정
        header('Content-Type: text/html; charset=UTF-8');
        include __DIR__ . '/../views/auth/login.php';
    }
}
