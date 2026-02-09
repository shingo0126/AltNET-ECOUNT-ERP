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
                    redirect('?page=dashboard');
                } else {
                    $error = $result['message'];
                }
            }
        }
        
        include __DIR__ . '/../views/auth/login.php';
    }
}
