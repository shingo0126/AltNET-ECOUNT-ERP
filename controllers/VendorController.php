<?php
class VendorController {
    
    public function index() {
        $db = Database::getInstance();
        $search = getParam('search', '');
        
        $where = "is_deleted=0";
        $params = [];
        if ($search) {
            $where .= " AND (name LIKE ? OR contact_person LIKE ? OR phone LIKE ?)";
            $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
        }
        
        $totalCount = $db->count('vendors', $where, $params);
        $vendors = $db->fetchAll("SELECT * FROM vendors WHERE $where ORDER BY name", $params);
        
        $pageTitle = '매입 업체 관리';
        ob_start();
        include __DIR__ . '/../views/vendors/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('?page=vendors');
        if (!CSRF::verify()) redirect('?page=vendors');
        Auth::requireRole(['admin', 'manager']);
        
        $db = Database::getInstance();
        $id = (int)postParam('id');
        
        $data = [
            'name'           => trim(postParam('name', '')),
            'contact_person' => trim(postParam('contact_person', '')),
            'phone'          => trim(postParam('phone', '')),
            'email'          => trim(postParam('email', '')),
            'zipcode'        => trim(postParam('zipcode', '')),
            'address'        => trim(postParam('address', '')),
            'address_detail' => trim(postParam('address_detail', '')),
        ];
        
        if (empty($data['name'])) {
            Session::set('flash_message', '업체명을 입력하세요.');
            Session::set('flash_type', 'danger');
            redirect('?page=vendors');
        }
        
        if ($id > 0) {
            $old = $db->fetch("SELECT * FROM vendors WHERE id=?", [$id]);
            $db->update('vendors', $data, 'id=?', [$id]);
            AuditLog::log('UPDATE', 'vendors', $id, $old, $data);
        } else {
            $id = $db->insert('vendors', $data);
            AuditLog::log('INSERT', 'vendors', $id, null, $data);
        }
        
        Session::set('flash_message', '매입 업체가 저장되었습니다.');
        Session::set('flash_type', 'success');
        redirect('?page=vendors');
    }
    
    public function delete() {
        Auth::requireRole(['admin', 'manager']);
        if (!CSRF::verify($_GET['token'] ?? '')) redirect('?page=vendors');
        
        $db = Database::getInstance();
        $id = (int)getParam('id');
        $old = $db->fetch("SELECT * FROM vendors WHERE id=?", [$id]);
        
        if ($old) {
            $db->update('vendors', ['is_deleted' => 1], 'id=?', [$id]);
            AuditLog::log('DELETE', 'vendors', $id, $old, null, '매입업체 삭제');
        }
        redirect('?page=vendors');
    }
    
    public function export() {
        $db = Database::getInstance();
        $vendors = $db->fetchAll("SELECT * FROM vendors WHERE is_deleted=0 ORDER BY name");
        AuditLog::log('EXPORT', 'vendors', null, null, null, '매입업체 CSV 다운로드');
        
        $headers = ['업체명', '담당자', '연락처', '이메일', '우편번호', '주소', '상세주소'];
        $rows = [];
        foreach ($vendors as $v) {
            $rows[] = [$v['name'], $v['contact_person'], $v['phone'], $v['email'], $v['zipcode'], $v['address'], $v['address_detail']];
        }
        csvExport('매입업체목록.csv', $headers, $rows);
    }
}
