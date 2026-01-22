<?php
// load_license.php - 讀取License資料

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();
    
    // 支援GET和POST兩種方式
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $objectId = $_GET['objectId'] ?? null;
        $serial_code = $_GET['serial_code'] ?? null;
        $user_name = $_GET['user_name'] ?? null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $objectId = $input['objectId'] ?? null;
        $serial_code = $input['serial_code'] ?? null;
        $user_name = $input['user_name'] ?? null;
    }
    
    // 根據不同條件查詢
    if ($objectId) {
        // 根據objectId查詢單筆資料
        $sql = "SELECT * FROM License WHERE objectId = :objectId";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':objectId' => $objectId]);
        $result = $stmt->fetch();
        
        if ($result) {
            // 轉換布林值
            $result['active'] = (bool)$result['active'];
            $result['infinity'] = (bool)$result['infinity'];
            $result['count'] = (int)$result['count'];
            
            sendResponse(true, 'License found', $result);
        } else {
            sendResponse(false, 'License not found', null, 404);
        }
        
    } elseif ($serial_code) {
        // 根據serial_code查詢
        $sql = "SELECT * FROM License WHERE serial_code = :serial_code";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':serial_code' => $serial_code]);
        $result = $stmt->fetch();
        
        if ($result) {
            $result['active'] = (bool)$result['active'];
            $result['infinity'] = (bool)$result['infinity'];
            $result['count'] = (int)$result['count'];
            
            sendResponse(true, 'License found', $result);
        } else {
            sendResponse(false, 'License not found', null, 404);
        }
        
    } elseif ($user_name) {
        // 根據user_name查詢（可能多筆）
        $sql = "SELECT * FROM License WHERE user_name = :user_name";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user_name' => $user_name]);
        $results = $stmt->fetchAll();
        
        foreach ($results as &$result) {
            $result['active'] = (bool)$result['active'];
            $result['infinity'] = (bool)$result['infinity'];
            $result['count'] = (int)$result['count'];
        }
        
        sendResponse(true, 'Found ' . count($results) . ' licenses', $results);
        
    } else {
        // 查詢全部（可加分頁）
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $sql = "SELECT * FROM License LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        foreach ($results as &$result) {
            $result['active'] = (bool)$result['active'];
            $result['infinity'] = (bool)$result['infinity'];
            $result['count'] = (int)$result['count'];
        }
        
        sendResponse(true, 'Found ' . count($results) . ' licenses', $results);
    }
    
} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>