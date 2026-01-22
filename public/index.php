<?php
// public/index.php - API 路由器

// 獲取請求 URI
$request_uri = $_SERVER['REQUEST_URI'];

// 移除查詢字符串，保留路徑
$path = parse_url($request_uri, PHP_URL_PATH);

// 移除開頭的斜線
$path = ltrim($path, '/');

// 記錄調試信息（開發時可用）
$debug_mode = false; // 部署後設為 false

if ($debug_mode) {
    error_log("Request URI: " . $request_uri);
    error_log("Parsed Path: " . $path);
}

// 檢查是否是 API 請求
if (strpos($path, 'api/') === 0) {
    // 提取 API 文件名
    $api_file = substr($path, 4); // 移除 'api/' 前綴
    
    // 安全檢查：防止目錄遍歷攻擊
    if (strpos($api_file, '..') !== false || strpos($api_file, '/') !== false) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden: Invalid path'
        ]);
        exit;
    }
    
    // 構建完整文件路徑
    $file_path = __DIR__ . '/../api/' . $api_file;
    
    if ($debug_mode) {
        error_log("Looking for file: " . $file_path);
        error_log("File exists: " . (file_exists($file_path) ? 'YES' : 'NO'));
    }
    
    // 檢查文件是否存在
    if (file_exists($file_path) && is_file($file_path)) {
        // 執行 API 文件
        require $file_path;
        exit;
    } else {
        // 文件不存在 - 返回詳細錯誤
        http_response_code(404);
        header('Content-Type: application/json');
        
        $error_response = [
            'success' => false,
            'message' => 'API endpoint not found',
            'requested_path' => $path,
            'api_file' => $api_file
        ];
        
        // 調試模式下顯示更多信息
        if ($debug_mode) {
            $error_response['debug'] = [
                'file_path_checked' => $file_path,
                'current_dir' => __DIR__,
                'api_dir_exists' => is_dir(__DIR__ . '/../api'),
                'available_files' => is_dir(__DIR__ . '/../api') 
                    ? scandir(__DIR__ . '/../api') 
                    : []
            ];
        }
        
        echo json_encode($error_response, JSON_PRETTY_PRINT);
        exit;
    }
}

// 如果訪問根路徑，顯示 API 文檔
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'VR System API',
    'version' => '1.0.0',
    'status' => 'running',
    'endpoints' => [
        'license' => [
            'save' => 'POST /api/save_license.php',
            'load' => 'GET /api/load_license.php?serial_code=XXX',
            'update' => 'POST /api/update_license.php'
        ],
        'vr_record' => [
            'save' => 'POST /api/save_vr_record.php',
            'load' => 'GET /api/load_vr_record.php?player_id=XXX'
        ],
        'import' => [
            'license' => 'POST /api/import_license.php'
        ]
    ],
    'examples' => [
        [
            'method' => 'GET',
            'url' => '/api/load_license.php?serial_code=TEST-001',
            'description' => 'Load license by serial code'
        ],
        [
            'method' => 'POST',
            'url' => '/api/save_license.php',
            'description' => 'Save new license',
            'body' => [
                'serial_code' => 'TEST-001',
                'user_name' => 'Test User',
                'active' => true,
                'count' => 100
            ]
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
