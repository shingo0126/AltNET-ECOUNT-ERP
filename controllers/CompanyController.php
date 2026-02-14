<?php
class CompanyController {
    
    public function index() {
        $db = Database::getInstance();
        $search = getParam('search', '');
        $page = max(1, (int)getParam('p', 1));
        $perPage = 25;
        
        $where = "is_deleted=0";
        $params = [];
        if ($search) {
            $where .= " AND (name LIKE ? OR contact_person LIKE ? OR phone LIKE ?)";
            $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
        }
        
        $totalCount = $db->count('companies', $where, $params);
        $pag = paginate($totalCount, $perPage, $page);
        $companies = $db->fetchAll(
            "SELECT * FROM companies WHERE $where ORDER BY name LIMIT {$pag['per_page']} OFFSET {$pag['offset']}",
            $params
        );
        
        $pageTitle = '매출 업체 관리';
        ob_start();
        include __DIR__ . '/../views/companies/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../views/layouts/main.php';
    }
    
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('?page=companies');
        if (!CSRF::verify()) redirect('?page=companies');
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
            redirect('?page=companies');
        }
        
        if ($id > 0) {
            $old = $db->fetch("SELECT * FROM companies WHERE id=?", [$id]);
            $db->update('companies', $data, 'id=?', [$id]);
            AuditLog::log('UPDATE', 'companies', $id, $old, $data);
        } else {
            $id = $db->insert('companies', $data);
            AuditLog::log('INSERT', 'companies', $id, null, $data);
        }
        
        Session::set('flash_message', '업체가 저장되었습니다.');
        Session::set('flash_type', 'success');
        redirect('?page=companies');
    }
    
    public function delete() {
        Auth::requireRole(['admin', 'manager']);
        if (!CSRF::verify($_GET['token'] ?? '')) redirect('?page=companies');
        
        $db = Database::getInstance();
        $id = (int)getParam('id');
        $old = $db->fetch("SELECT * FROM companies WHERE id=?", [$id]);
        
        if ($old) {
            $db->update('companies', ['is_deleted' => 1], 'id=?', [$id]);
            AuditLog::log('DELETE', 'companies', $id, $old, null, '매출업체 삭제');
        }
        
        redirect('?page=companies');
    }
    
    public function export() {
        $db = Database::getInstance();
        $companies = $db->fetchAll("SELECT * FROM companies WHERE is_deleted=0 ORDER BY name");
        
        AuditLog::log('EXPORT', 'companies', null, null, null, '매출업체 CSV 다운로드');
        
        $headers = ['업체명', '담당자', '연락처', '이메일', '우편번호', '주소', '상세주소'];
        $rows = [];
        foreach ($companies as $c) {
            $rows[] = [$c['name'], $c['contact_person'], $c['phone'], $c['email'], $c['zipcode'], $c['address'], $c['address_detail']];
        }
        csvExport('매출업체목록.csv', $headers, $rows);
    }
}
