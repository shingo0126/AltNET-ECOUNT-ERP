<?php
/**
 * AltNET Ecount ERP - Application Configuration
 */
return [
    'name'            => 'AltNET ECOUNT ERP',
    'version'         => '1.0.0',
    'base_url'        => getenv('APP_URL') ?: '',
    'session_timeout' => 1800, // 30 minutes
    'session_warning' => 300,  // 5 minutes before timeout
    'login_max_fail'  => 5,
    'lock_duration'   => 300,  // 5 minutes in seconds
    'soft_delete_days' => 15,  // Days before permanent delete
    'per_page'        => 30,
    'backup_dir'      => __DIR__ . '/../backups/',
    'log_dir'         => __DIR__ . '/../logs/',
    'upload_max_size' => 50 * 1024 * 1024, // 50MB
    'colors' => [
        'primary'    => '#A7C4AA',
        'accent'     => '#0077B6',
        'background' => '#FDFCF0',
        'text'       => '#4A5043',
    ],
];
