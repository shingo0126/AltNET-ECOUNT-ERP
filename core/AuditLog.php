<?php
/**
 * AltNET Ecount ERP - Audit Logger
 */
class AuditLog {
    
    public static function log($action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null, $description = null) {
        try {
            $db = Database::getInstance();
            $db->insert('audit_logs', [
                'user_id'     => Session::getUserId(),
                'username'    => Session::get('username', 'system'),
                'action'      => $action,
                'table_name'  => $tableName,
                'record_id'   => $recordId,
                'old_values'  => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values'  => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'description' => $description,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
        } catch (Exception $e) {
            error_log("AuditLog Error: " . $e->getMessage());
        }
    }
}
