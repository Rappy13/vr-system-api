<?php
// 重定向所有請求到 API
$request_uri = $_SERVER['REQUEST_URI'];

// 移除開頭的 /
$path = ltrim($request_uri, '/');

// 如果路徑包含 api/
if (strpos($path, 'api/') === 0) {
    // 提取 API 文件名
    $api_file = str_replace('api/', '', $path);
    $api_file = strtok($api_file, '?'); // 移除查詢參數
    
    $file_path = __DIR__ . '/../api/' . $api_file;
    
    if (file_exists($file_path)) {
        require $file_path;
        exit;
    }
}

// 預設回應
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'VR System API is running',
    'endpoints' => [
        'POST /api/save_license.php',
        'GET /api/load_license.php',
        'POST /api/update_license.php',
        'POST /api/save_vr_record.php',
        'GET /api/load_vr_record.php'
    ]
]);
