<?php

/**
 * File cấu hình hệ thống SimpleFinance (MySQL + Xác thực Admin)
 * Bạn hãy thay đổi các thông số dưới đây phù hợp với môi trường của mình.
 */

return [
    // 1. Cấu hình Cơ sở Dữ liệu MySQL
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

    // 2. Cấu hình MCP Server
    'server' => [
        'name'    => 'simplefinance-mcp',
        'version' => '2.0.0',
    ],

    // 3. Khóa bí mật mã hóa dữ liệu AES-256-CBC (App Key)
    'app_key' => 'sf_sec_e8b9f2c41a7d6e503b8c2d1f9e4a7b5c',

    // 4. Cấu hình Tự Động Triển Khai (Auto Deploy / Webhook API)
    'deploy' => [
        'enabled'      => true,
        'secret_token' => 'sf_dep_8f1a3b5c7e9d2f4a6b8c0e1d3f5a7b9c',
        'branch'       => 'main', // Nhánh git mặc định
    ],

    // 5. Cơ chế Đăng nhập Bảo mật bằng "Ký ức Thời gian"
    // Danh sách các mốc sự kiện cá nhân (Định dạng: Ngày-Tháng-Năm DD-MM-YYYY)
    // Hệ thống sẽ bốc ngẫu nhiên câu hỏi (hỏi ngày, tháng, hoặc năm)
    'timeline_events' => [
        'sinh nhật của tôi'    => '14-07-1986',
        'sinh nhật của vợ tôi' => '21-09-1995',
        'kỷ niệm ngày cưới'    => '20-03-2021',
        'ngày tỏ tình'         => '22-11-2019',
        'sinh nhật mẹ tôi'     => '20-05-1960',
        'sinh nhật bố tôi'     => '10-10-1955',
    ],
];
