<?php
/**
 * AltNET Ecount ERP - Helper Functions
 */

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount) {
    return number_format((int)$amount);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getParam($key, $default = null) {
    return $_GET[$key] ?? $default;
}

function postParam($key, $default = null) {
    return $_POST[$key] ?? $default;
}

function generateSaleNumber($date = null) {
    $db = Database::getInstance();
    if (!$date) $date = date('Y-m-d');
    $ym = date('Ym', strtotime($date));
    $prefix = "ALT-{$ym}-";
    
    $last = $db->fetch(
        "SELECT sale_number FROM sales WHERE sale_number LIKE ? ORDER BY sale_number DESC LIMIT 1",
        [$prefix . '%']
    );
    
    if ($last) {
        $num = (int)substr($last['sale_number'], -4) + 1;
    } else {
        $num = 1;
    }
    
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function generatePurchaseNumber($date = null) {
    $db = Database::getInstance();
    if (!$date) $date = date('Y-m-d');
    $ym = date('Ym', strtotime($date));
    $prefix = "PUR-{$ym}-";
    
    $last = $db->fetch(
        "SELECT purchase_number FROM purchases WHERE purchase_number LIKE ? ORDER BY purchase_number DESC LIMIT 1",
        [$prefix . '%']
    );
    
    if ($last) {
        $num = (int)substr($last['purchase_number'], -4) + 1;
    } else {
        $num = 1;
    }
    
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function paginate($total, $perPage, $currentPage) {
    $totalPages = max(1, ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
    ];
}

function csvExport($filename, $headers, $data) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
