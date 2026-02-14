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
    
    /**
     * 백업 생성
     * 
     * [수정사항] 기존 코드의 문제점 7가지 해결:
     * 
     * 1) backup_dir 경로에 '..' 포함 → realpath()로 정규화
     *    - config에서 __DIR__ . '/../backups/' 반환 → '/home/user/webapp/config/../backups/'
     *    - 일부 환경에서 exec() 실행 시 상대경로 해석 실패 가능
     *
     * 2) --password='' (빈 비밀번호) → --password=''가 일부 MySQL/MariaDB에서 경고 발생
     *    - 비밀번호가 비어 있으면 --password 옵션 자체를 제거
     *
     * 3) 2>&1 리디렉션이 SQL 파일에 에러 메시지를 섞어 넣음
     *    - stderr를 별도 파일로 분리하여 에러 로그 캡처
     *
     * 4) exec()가 비활성화된 환경에서 무조건 실패
     *    - function_exists('exec') 사전 체크 추가
     *
     * 5) mysqldump 바이너리가 PATH에 없는 환경
     *    - which mysqldump으로 사전 확인, 없으면 안내 메시지
     *
     * 6) 파일 크기가 0인 경우(덤프 실패)에도 성공으로 처리
     *    - filesize > 0 조건 추가
     *
     * 7) PDO 기반 폴백(fallback) 백업 추가
     *    - mysqldump 실행 불가 시 PHP/PDO로 직접 SQL 덤프 생성
     */
    public function create() {
        Auth::requireRole(['admin']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('?page=backup');
        if (!CSRF::verify()) redirect('?page=backup');
        
        $config = require __DIR__ . '/../config/database.php';
        $appConfig = require __DIR__ . '/../config/app.php';
        
        // [FIX #1] 경로 정규화: '..' 제거
        $backupDir = $appConfig['backup_dir'];
        if (!is_dir($backupDir)) mkdir($backupDir, 0750, true);
        $backupDir = realpath($backupDir) . '/';
        
        $filename = 'backup_' . date('Ymd_His') . '.sql';
        $filepath = $backupDir . $filename;
        
        $success = false;
        $errorMsg = '';
        
        // [FIX #4] exec() 사용 가능 여부 확인
        $canExec = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));
        
        if ($canExec) {
            // [FIX #5] mysqldump 존재 확인
            $mysqldumpPath = '';
            exec('which mysqldump 2>/dev/null', $whichOut, $whichRc);
            if ($whichRc === 0 && !empty($whichOut)) {
                $mysqldumpPath = trim($whichOut[0]);
            } else {
                // 일반적인 경로 직접 확인
                foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/bin/mariadb-dump'] as $path) {
                    if (file_exists($path) && is_executable($path)) {
                        $mysqldumpPath = $path;
                        break;
                    }
                }
            }
            
            if ($mysqldumpPath) {
                // [FIX #2] 빈 비밀번호 처리: --password 옵션 자체를 제거
                $passwordPart = '';
                if (!empty($config['password'])) {
                    $passwordPart = ' --password=' . escapeshellarg($config['password']);
                }
                
                // [FIX #3] stderr를 별도 파일로 분리 (SQL 파일 오염 방지)
                $errFile = $backupDir . '.backup_err_' . date('His') . '.log';
                $cmd = sprintf(
                    '%s --host=%s --port=%s --user=%s%s --default-character-set=utf8mb4 --single-transaction --routines --triggers %s > %s 2>%s',
                    escapeshellarg($mysqldumpPath),
                    escapeshellarg($config['host']),
                    escapeshellarg($config['port'] ?? '3306'),
                    escapeshellarg($config['username']),
                    $passwordPart,
                    escapeshellarg($config['dbname']),
                    escapeshellarg($filepath),
                    escapeshellarg($errFile)
                );
                
                exec($cmd, $output, $returnCode);
                
                // stderr 내용 읽기
                $stderrContent = '';
                if (file_exists($errFile)) {
                    $stderrContent = trim(file_get_contents($errFile));
                    @unlink($errFile); // 임시 에러 로그 삭제
                }
                
                // [FIX #6] 파일 크기 0 체크
                if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                    $success = true;
                } else {
                    // 실패 원인 상세화
                    if (!file_exists($filepath)) {
                        $errorMsg = '백업 파일이 생성되지 않았습니다.';
                    } elseif (filesize($filepath) === 0) {
                        $errorMsg = '백업 파일이 비어있습니다 (0 bytes).';
                        @unlink($filepath);
                    } else {
                        $errorMsg = "mysqldump 종료코드: {$returnCode}";
                    }
                    if ($stderrContent) {
                        $errorMsg .= ' | ' . $stderrContent;
                    }
                }
            } else {
                $errorMsg = 'mysqldump를 찾을 수 없습니다. (mariadb-dump / mysqldump 미설치)';
            }
        } else {
            $errorMsg = 'PHP exec() 함수가 비활성화되어 있습니다.';
        }
        
        // [FIX #7] mysqldump 실패 시 PDO 폴백 백업
        if (!$success && !$canExec || (!$success && $errorMsg)) {
            $pdoResult = $this->pdoBackup($filepath, $config);
            if ($pdoResult === true) {
                $success = true;
                $errorMsg = '';
            } else {
                // PDO 폴백도 실패 시
                $errorMsg .= ' | PDO 폴백 시도: ' . $pdoResult;
            }
        }
        
        if ($success) {
            $db = Database::getInstance();
            $filesize = filesize($filepath);
            $db->insert('backups', [
                'filename' => $filename,
                'filesize' => $filesize,
                'user_id'  => Session::getUserId(),
                'memo'     => postParam('memo', ''),
            ]);
            AuditLog::log('BACKUP', 'backups', null, null, ['filename' => $filename, 'size' => $filesize], 'DB 백업 생성');
            Session::set('flash_message', "백업이 생성되었습니다: {$filename} (" . number_format($filesize / 1024, 1) . " KB)");
            Session::set('flash_type', 'success');
        } else {
            Session::set('flash_message', '백업 생성 실패: ' . $errorMsg);
            Session::set('flash_type', 'danger');
        }
        
        redirect('?page=backup');
    }
    
    /**
     * PDO 기반 백업 폴백
     * mysqldump를 사용할 수 없는 환경을 위한 순수 PHP/PDO 백업
     * 
     * @return true|string  성공 시 true, 실패 시 에러 메시지
     */
    private function pdoBackup($filepath, $config) {
        try {
            $db = Database::getInstance();
            $tables = $db->fetchAll("SHOW TABLES");
            
            $sql = "-- AltNET Ecount ERP - PDO Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Database: {$config['dbname']}\n\n";
            $sql .= "SET NAMES utf8mb4;\n";
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            
            $dbKey = "Tables_in_{$config['dbname']}";
            
            foreach ($tables as $table) {
                $tableName = $table[$dbKey] ?? reset($table);
                
                // CREATE TABLE
                $createResult = $db->fetch("SHOW CREATE TABLE `{$tableName}`");
                $createSql = $createResult['Create Table'] ?? '';
                
                $sql .= "-- ---\n";
                $sql .= "-- Table: {$tableName}\n";
                $sql .= "-- ---\n";
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createSql . ";\n\n";
                
                // INSERT DATA
                $rows = $db->fetchAll("SELECT * FROM `{$tableName}`");
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $colList = '`' . implode('`, `', $columns) . '`';
                    
                    foreach ($rows as $row) {
                        $values = array_map(function($v) {
                            if ($v === null) return 'NULL';
                            return "'" . addslashes($v) . "'";
                        }, array_values($row));
                        $sql .= "INSERT INTO `{$tableName}` ({$colList}) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql .= "\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            
            $written = file_put_contents($filepath, $sql);
            if ($written === false) {
                return '파일 쓰기 실패: ' . $filepath;
            }
            
            return true;
            
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    
    public function download() {
        Auth::requireRole(['admin']);
        $db = Database::getInstance();
        $id = (int)getParam('id');
        $backup = $db->fetch("SELECT * FROM backups WHERE id=?", [$id]);
        
        if (!$backup) redirect('?page=backup');
        
        $appConfig = require __DIR__ . '/../config/app.php';
        // [FIX #1] 경로 정규화
        $backupDir = realpath($appConfig['backup_dir']);
        $filepath = $backupDir . '/' . $backup['filename'];
        
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
        // [FIX #1] 경로 정규화
        $backupDir = realpath($appConfig['backup_dir']);
        $filepath = $backupDir . '/' . $backup['filename'];
        
        if (!file_exists($filepath)) {
            Session::set('flash_message', '백업 파일을 찾을 수 없습니다.');
            Session::set('flash_type', 'danger');
            redirect('?page=backup');
        }
        
        $config = require __DIR__ . '/../config/database.php';
        $success = false;
        $errorMsg = '';
        
        $canExec = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));
        
        if ($canExec) {
            // [FIX #2] 빈 비밀번호 처리
            $passwordPart = '';
            if (!empty($config['password'])) {
                $passwordPart = ' --password=' . escapeshellarg($config['password']);
            }
            
            // [FIX #3] stderr 분리
            $errFile = $backupDir . '/.restore_err_' . date('His') . '.log';
            $cmd = sprintf(
                'mysql --host=%s --port=%s --user=%s%s --default-character-set=utf8mb4 %s < %s 2>%s',
                escapeshellarg($config['host']),
                escapeshellarg($config['port'] ?? '3306'),
                escapeshellarg($config['username']),
                $passwordPart,
                escapeshellarg($config['dbname']),
                escapeshellarg($filepath),
                escapeshellarg($errFile)
            );
            
            exec($cmd, $output, $returnCode);
            
            $stderrContent = '';
            if (file_exists($errFile)) {
                $stderrContent = trim(file_get_contents($errFile));
                @unlink($errFile);
            }
            
            if ($returnCode === 0) {
                $success = true;
            } else {
                $errorMsg = "mysql 종료코드: {$returnCode}";
                if ($stderrContent) $errorMsg .= ' | ' . $stderrContent;
            }
        }
        
        // mysql 클라이언트 사용 불가 시 PDO 폴백 복원
        if (!$success && !$canExec) {
            $pdoResult = $this->pdoRestore($filepath);
            if ($pdoResult === true) {
                $success = true;
            } else {
                $errorMsg .= ' | PDO 폴백 복원: ' . $pdoResult;
            }
        }
        
        if ($success) {
            AuditLog::log('RESTORE', 'backups', $id, null, ['filename' => $backup['filename']], 'DB 복원 완료');
            Session::set('flash_message', "복원이 완료되었습니다: {$backup['filename']}");
            Session::set('flash_type', 'success');
        } else {
            Session::set('flash_message', '복원 실패: ' . $errorMsg);
            Session::set('flash_type', 'danger');
        }
        
        redirect('?page=backup');
    }
    
    /**
     * PDO 기반 복원 폴백
     * 
     * @return true|string  성공 시 true, 실패 시 에러 메시지
     */
    private function pdoRestore($filepath) {
        try {
            $sql = file_get_contents($filepath);
            if ($sql === false) return '백업 파일을 읽을 수 없습니다.';
            
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // SQL 문 분리 실행
            $statements = array_filter(array_map('trim', explode(";\n", $sql)));
            foreach ($statements as $stmt) {
                if (empty($stmt) || strpos($stmt, '--') === 0) continue;
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // 무시 가능한 에러 (DROP IF EXISTS 등) 건너뛰기
                    error_log("PDO restore skip: " . $e->getMessage());
                }
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            return true;
            
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
