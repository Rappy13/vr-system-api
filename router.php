<?php
// router.php - 簡單的路由器，供 PHP 內建伺服器使用

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 如果是真實文件，直接返回
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

// 處理 API 請求
if (preg_match('/^\/api\/(.+\.php)/', $uri, $matches)) {
    $api_file = __DIR__ . '/api/' . $matches[1];
    
    if (file_exists($api_file)) {
        require $api_file;
        exit;
    }
    
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'API not found']);
    exit;
}

// 其他請求交給 public/index.php
require __DIR__ . '/public/index.php';
?>
