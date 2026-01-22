<?php
// debug.php - 診斷工具（部署完成後應刪除此文件）

header('Content-Type: application/json');

$info = [
    'php_version' => phpversion(),
    'current_dir' => __DIR__,
    'parent_dir' => dirname(__DIR__),
    'api_dir' => __DIR__ . '/../api',
    'api_dir_exists' => is_dir(__DIR__ . '/../api'),
    'api_files' => [],
    'public_files' => scandir(__DIR__),
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
];

// 列出 API 目錄中的文件
if (is_dir(__DIR__ . '/../api')) {
    $api_files = scandir(__DIR__ . '/../api');
    foreach ($api_files as $file) {
        if ($file != '.' && $file != '..') {
            $full_path = __DIR__ . '/../api/' . $file;
            $info['api_files'][$file] = [
                'exists' => file_exists($full_path),
                'is_file' => is_file($full_path),
                'is_readable' => is_readable($full_path),
                'size' => file_exists($full_path) ? filesize($full_path) : 0
            ];
        }
    }
}

// 檢查環境變數
$info['environment'] = [
    'DB_HOST' => getenv('DB_HOST') ? 'SET' : 'NOT SET',
    'DB_NAME' => getenv('DB_NAME') ? 'SET' : 'NOT SET',
    'DB_USER' => getenv('DB_USER') ? 'SET' : 'NOT SET',
    'DB_PASS' => getenv('DB_PASS') ? 'SET' : 'NOT SET',
];

// 測試數據庫連接
if (getenv('DB_HOST') && getenv('DB_NAME')) {
    try {
        $conn = new PDO(
            "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME'),
            getenv('DB_USER'),
            getenv('DB_PASS')
        );
        $info['database_connection'] = 'SUCCESS';
    } catch (PDOException $e) {
        $info['database_connection'] = 'FAILED: ' . $e->getMessage();
    }
}

echo json_encode($info, JSON_PRETTY_PRINT);
?>
