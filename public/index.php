<?php
// public/index.php - API 路由器（修正版）

// 設置錯誤報告（調試用）
error_reporting(E_ALL);
ini_set('display_errors', 0); // 生產環境關閉顯示錯誤

// 獲取請求 URI 和查詢字符串
$request_uri = $_SERVER['REQUEST_URI'];
$query_string = $_SERVER['QUERY_STRING'] ?? '';

// 使用 parse_url 正確解析路徑
$parsed = parse_url($request_uri);
$path = $parsed['path'] ?? '/';

// 移除開頭的斜線
$path = ltrim($path, '/');

// 檢查是否是 API 請求
if (strpos($path, 'api/') === 0) {
    // 提取 API 文件名（移除 'api/' 前綴）
    $api_file = substr($path, 4);
    
    // 安全檢查：防止目錄遍歷
    if (strpos($api_file, '..') !== false) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
    
    // 只允許 .php 文件
    if (!preg_match('/\.php$/', $api_file)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    // 構建文件路徑
    $file_path = __DIR__ . '/../api/' . $api_file;
    
    // 檢查文件是否存在
    if (file_exists($file_path) && is_file($file_path)) {
        // ⚠️ 重要：恢復查詢字符串到 $_GET
        if (!empty($query_string)) {
            parse_str($query_string, $_GET);
        }
        
        // 執行 API 文件
        require $file_path;
        exit;
    } else {
        // 文件不存在
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'API endpoint not found',
            'path' => $path,
            'file' => $api_file
        ]);
        exit;
    }
}

// 預設首頁 - API 文檔
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'VR System API',
    'version' => '1.0.0',
    'status' => 'running',
    'server_time' => date('Y-m-d H:i:s'),
    'endpoints' => [
        'license' => [
            'save' => 'POST /api/save_license.php',
            'load' => 'GET /api/load_license.php?serial_code=XXX',
            'load_by_objectId' => 'GET /api/load_license.php?objectId=XXX',
            'update' => 'POST /api/update_license.php'
        ],
        'vr_record' => [
            'save' => 'POST /api/save_vr_record.php',
            'load' => 'GET /api/load_vr_record.php?player_id=XXX'
        ],
        'import' => 'POST /api/import_license.php'
    ],
    'examples' => [
        'Load License' => 'GET /api/load_license.php?serial_code=TEST-001',
        'Save License' => 'POST /api/save_license.php with JSON body'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
