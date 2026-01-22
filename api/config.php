<?php
// config.php - InfinityFree 資料庫配置

// ⚠️ 請替換為您的資料庫資訊
define('DB_HOST', 'sql123.infinityfree.com');      // 您的資料庫主機名
define('DB_NAME', 'epiz_34567890_vr_system');      // 您的資料庫名稱
define('DB_USER', 'epiz_34567890');                 // 您的資料庫用戶名
define('DB_PASS', 'your_password_here');            // 您的資料庫密碼

// 創建資料庫連接
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed'
            // 生產環境不顯示詳細錯誤訊息
        ]);
        exit();
    }
}

// 生成23碼唯一ObjectId
function generateObjectId() {
    $timestamp = dechex(time());
    $random = bin2hex(random_bytes(8));
    $objectId = strtoupper(substr($timestamp . $random, 0, 23));
    
    while (strlen($objectId) < 23) {
        $objectId .= strtoupper(dechex(mt_rand(0, 15)));
    }
    
    return substr($objectId, 0, 23);
}

// 驗證ObjectId是否唯一
function isObjectIdUnique($conn, $tableName, $objectId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM {$tableName} WHERE objectId = :objectId");
    $stmt->execute([':objectId' => $objectId]);
    return $stmt->fetchColumn() == 0;
}

// 設置CORS（允許Unity訪問）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// 處理OPTIONS預檢請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 標準響應函數
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
?>