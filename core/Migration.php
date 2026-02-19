<?php
/**
 * AltNET Ecount ERP - Database Migration System
 * 
 * 앱 부팅 시 자동으로 미적용 마이그레이션을 실행합니다.
 * migrations/ 디렉토리에 YYYYMMDD_HHMMSS_description.php 형식의 파일을 추가하면
 * 다음 요청 시 자동 적용됩니다.
 */
class Migration {
    private $db;
    private $migrationsPath;
    private $logFile;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->migrationsPath = __DIR__ . '/../migrations';
        $this->logFile = __DIR__ . '/../logs/migration.log';
    }

    /**
     * migrations 테이블 존재 확인 및 생성
     */
    private function ensureMigrationTable(): void {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(300) NOT NULL,
                `batch` INT(11) NOT NULL DEFAULT 1,
                `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `migration_unique` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * 이미 적용된 마이그레이션 목록 조회
     */
    private function getAppliedMigrations(): array {
        $rows = $this->db->fetchAll("SELECT migration FROM migrations ORDER BY id ASC");
        return array_column($rows, 'migration');
    }

    /**
     * migrations/ 디렉토리에서 마이그레이션 파일 목록 조회 (정렬됨)
     */
    private function getMigrationFiles(): array {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.php');
        if (!$files) {
            return [];
        }

        // 파일명 기준 정렬 (타임스탬프 순서)
        sort($files);

        $migrations = [];
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            $migrations[$filename] = $file;
        }

        return $migrations;
    }

    /**
     * 미적용 마이그레이션 실행
     * @return array 실행 결과 [applied => [], errors => []]
     */
    public function run(): array {
        $this->ensureMigrationTable();

        $applied = $this->getAppliedMigrations();
        $files = $this->getMigrationFiles();
        $result = ['applied' => [], 'errors' => [], 'skipped' => []];

        if (empty($files)) {
            return $result;
        }

        // 현재 배치 번호
        $batchRow = $this->db->fetch("SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations");
        $batch = (int)$batchRow['next_batch'];

        foreach ($files as $name => $filepath) {
            // 이미 적용된 마이그레이션은 건너뜀
            if (in_array($name, $applied)) {
                $result['skipped'][] = $name;
                continue;
            }

            try {
                $this->log("Applying migration: {$name}");

                // 마이그레이션 파일 로드
                $migration = require $filepath;

                // 마이그레이션 파일은 up() 메소드가 있는 배열 또는 객체를 반환해야 함
                if (is_array($migration) && isset($migration['up'])) {
                    $this->executeMigration($migration['up']);
                } elseif (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up($this->db);
                } else {
                    throw new Exception("Invalid migration format: {$name}. Must return array with 'up' key or object with up() method.");
                }

                // 적용 기록 저장
                $this->db->insert('migrations', [
                    'migration' => $name,
                    'batch' => $batch,
                ]);

                $result['applied'][] = $name;
                $this->log("Applied: {$name}");

            } catch (Exception $e) {
                $error = "Migration failed [{$name}]: " . $e->getMessage();
                $result['errors'][] = $error;
                $this->log("ERROR: {$error}");
                // 하나라도 실패하면 중단 (안전을 위해)
                break;
            }
        }

        return $result;
    }

    /**
     * SQL 문 배열 실행
     */
    private function executeMigration($sqlStatements): void {
        if (is_string($sqlStatements)) {
            $sqlStatements = [$sqlStatements];
        }

        foreach ($sqlStatements as $sql) {
            $sql = trim($sql);
            if (empty($sql)) continue;
            $this->db->query($sql);
        }
    }

    /**
     * 마이그레이션 상태 조회 (관리 페이지용)
     */
    public function getStatus(): array {
        $this->ensureMigrationTable();

        $applied = $this->getAppliedMigrations();
        $files = $this->getMigrationFiles();

        $status = [];
        foreach ($files as $name => $filepath) {
            $status[] = [
                'migration' => $name,
                'applied' => in_array($name, $applied),
            ];
        }

        // DB에는 있지만 파일이 없는 마이그레이션 (삭제된 파일)
        foreach ($applied as $name) {
            if (!isset($files[$name])) {
                $status[] = [
                    'migration' => $name,
                    'applied' => true,
                    'missing_file' => true,
                ];
            }
        }

        return $status;
    }

    /**
     * 로그 기록
     */
    private function log(string $message): void {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($this->logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * 미적용 마이그레이션 존재 여부 (빠른 체크)
     */
    public function hasPending(): bool {
        $this->ensureMigrationTable();
        $applied = $this->getAppliedMigrations();
        $files = $this->getMigrationFiles();

        foreach ($files as $name => $filepath) {
            if (!in_array($name, $applied)) {
                return true;
            }
        }
        return false;
    }
}
