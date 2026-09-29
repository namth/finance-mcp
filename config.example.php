<?php

/**
 * File mẫu cấu hình hệ thống SimpleFinance (MySQL + Xác thực Admin)
 */

return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => 'simplefinance',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],
    'server' => [
        'name'    => 'simplefinance-mcp',
        'version' => '2.0.0',
    ],
    'app_key' => 'replace_with_your_32_bytes_random_secret_key_here!!',
    'deploy' => [
        'enabled'      => true,
        'secret_token' => 'replace_with_your_deploy_secret_token_here',
        'branch'       => 'main',
    ],
    'timeline_events' => [
        'sinh nhật của tôi'        => '14-08-1983',
        'kỷ niệm ngày cưới'        => '21-08-2020',
        'ngày sinh con đầu lòng'   => '05-12-2015',
        'ngày mua xe ô tô'         => '10-03-2019',
        'ngày tốt nghiệp đại học'  => '30-06-2005',
        'ngày đi làm đầu tiên'     => '15-09-2006',
        'ngày dọn về nhà mới'      => '08-11-2021',
    ],
];
