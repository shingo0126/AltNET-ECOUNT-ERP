<?php
class BackupController {
    
    public function index() {
        Auth::requireRole(['admin']);
        $db = Database::getInstance();
        $backups = $db->fetchAll("SELECT b.*, u.name as user_name FROM backups b LEFT JOIN users u ON b.user_id=u.id ORDER BY b.created_at DESC");
        
        $pageTitle = '백업/복원';
        ob_start();
        include __DIR__ . '/../views/backup/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    public function create() {
        Auth::requireRole(['admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('?page=backup');
        if (!CSRF::verify()) redirect('?page=backup');
        
        $config = require __DIR__ . '/../config/database.php';
        $appConfig = require __DIR__ . '/../config/app.php';
        $backupDir = $appConfig['backup_dir'];
        
        if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);
        
        $filename = 'backup_' . date('Ymd_His') . '.sql';
        $filepath = $backupDir . $filename;
        
        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['dbname']),
            escapeshellarg($filepath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($filepath)) {
            $db = Database::getInstance();
            $filesize = filesize($filepath);
            $db->insert('backups', [
                'filename' => $filename,
                'filesize' => $filesize,
                'user_id'  => Session::getUserId(),
                'memo'     => postParam('memo', ''),
            ]);
            AuditLog::log('BACKUP', 'backups', null, null, ['filename' => $filename, 'size' => $filesize], 'DB 백업 생성');
            Session::set('flash_message', "백업이 생성되었습니다: {$filename}");
            Session::set('flash_type', 'success');
        } else {
            Session::set('flash_message', '백업 생성 실패: ' . implode("\n", $output));
            Session::set('flash_type', 'danger');
        }
        
        redirect('?page=backup');
    }
    
    public function download() {
        Auth::requireRole(['admin']);
        $db = Database::getInstance();
        $id = (int)getParam('id');
        $backup = $db->fetch("SELECT * FROM backups WHERE id=?", [$id]);
        
        if (!$backup) redirect('?page=backup');
        
        $appConfig = require __DIR__ . '/../config/app.php';
        $filepath = $appConfig['backup_dir'] . $backup['filename'];
        
        if (!file_exists($filepath)) {
            Session::set('flash_message', '백업 파일을 찾을 수 없습니다.');
            Session::set('flash_type', 'danger');
            redirect('?page=backup');
        }
        
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
    
    public function restore() {
        Auth::requireRole(['admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('?page=backup');
        if (!CSRF::verify()) redirect('?page=backup');
        
        // Step 2: verify password
        $password = postParam('password', '');
        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE id=?", [Session::getUserId()]);
        
        if (!password_verify($password, $user['password_hash'])) {
            Session::set('flash_message', '비밀번호가 일치하지 않습니다.');
            Session::set('flash_type', 'danger');
            redirect('?page=backup');
        }
        
        $id = (int)postParam('backup_id');
        $backup = $db->fetch("SELECT * FROM backups WHERE id=?", [$id]);
        if (!$backup) redirect('?page=backup');
        
        $appConfig = require __DIR__ . '/../config/app.php';
        $filepath = $appConfig['backup_dir'] . $backup['filename'];
        
        if (!file_exists($filepath)) {
            Session::set('flash_message', '백업 파일을 찾을 수 없습니다.');
            Session::set('flash_type', 'danger');
            redirect('?page=backup');
        }
        
        $config = require __DIR__ . '/../config/database.php';
        $cmd = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['dbname']),
            escapeshellarg($filepath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0) {
            AuditLog::log('RESTORE', 'backups', $id, null, ['filename' => $backup['filename']], 'DB 복원 완료');
            Session::set('flash_message', "복원이 완료되었습니다: {$backup['filename']}");
            Session::set('flash_type', 'success');
        } else {
            Session::set('flash_message', '복원 실패: ' . implode("\n", $output));
            Session::set('flash_type', 'danger');
        }
        
        redirect('?page=backup');
    }
}
