<?php
// load_vr_record.php - 讀取VR_Player_Record資料

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();
    
    // 支援GET和POST兩種方式
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $objectId = $_GET['objectId'] ?? null;
        $player_id = $_GET['player_id'] ?? null;
        $user = $_GET['user'] ?? null;
        $scene = isset($_GET['scene']) ? (int)$_GET['scene'] : null;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $objectId = $input['objectId'] ?? null;
        $player_id = $input['player_id'] ?? null;
        $user = $input['user'] ?? null;
        $scene = isset($input['scene']) ? (int)$input['scene'] : null;
    }
    
    // 根據不同條件查詢
    if ($objectId) {
        // 根據objectId查詢單筆資料
        $sql = "SELECT * FROM VR_Player_Record WHERE objectId = :objectId";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':objectId' => $objectId]);
        $result = $stmt->fetch();
        
        if ($result) {
            $result['age'] = $result['age'] ? (int)$result['age'] : null;
            $result['scene'] = $result['scene'] ? (int)$result['scene'] : null;
            $result['sexual'] = $result['sexual'] ? (int)$result['sexual'] : null;
            
            sendResponse(true, 'VR Player Record found', $result);
        } else {
            sendResponse(false, 'VR Player Record not found', null, 404);
        }
        
    } elseif ($player_id) {
        // 根據player_id查詢（可能多筆）
        $sql = "SELECT * FROM VR_Player_Record WHERE player_id = :player_id ORDER BY fake_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':player_id' => $player_id]);
        $results = $stmt->fetchAll();
        
        foreach ($results as &$result) {
            $result['age'] = $result['age'] ? (int)$result['age'] : null;
            $result['scene'] = $result['scene'] ? (int)$result['scene'] : null;
            $result['sexual'] = $result['sexual'] ? (int)$result['sexual'] : null;
        }
        
        sendResponse(true, 'Found ' . count($results) . ' records', $results);
        
    } elseif ($user) {
        // 根據user查詢（可能多筆）
        $sql = "SELECT * FROM VR_Player_Record WHERE user = :user ORDER BY fake_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user' => $user]);
        $results = $stmt->fetchAll();
        
        foreach ($results as &$result) {
            $result['age'] = $result['age'] ? (int)$result['age'] : null;
            $result['scene'] = $result['scene'] ? (int)$result['scene'] : null;
            $result['sexual'] = $result['sexual'] ? (int)$result['sexual'] : null;
        }
        
        sendResponse(true, 'Found ' . count($results) . ' records', $results);
        
    } elseif ($scene !== null) {
        // 根據scene查詢（可能多筆）
        $sql = "SELECT * FROM VR_Player_Record WHERE scene = :scene ORDER BY fake_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':scene' => $scene]);
        $results = $stmt->fetchAll();
        
        foreach ($results as &$result) {
            $result['age'] = $result['age'] ? (int)$result['age'] : null;
            $result['scene'] = $result['scene'] ? (int)$result['scene'] : null;
            $result['sexual'] = $result['sexual'] ? (int)$result['sexual'] : null;
        }
        
        sendResponse(true, 'Found ' . count($results) . ' records', $results);
        
    } else {
        // 查詢全部（可加分頁）
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $sql = "SELECT * FROM VR_Player_Record ORDER BY fake_time DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        foreach ($results as &$result) {
            $result['age'] = $result['age'] ? (int)$result['age'] : null;
            $result['scene'] = $result['scene'] ? (int)$result['scene'] : null;
            $result['sexual'] = $result['sexual'] ? (int)$result['sexual'] : null;
        }
        
        sendResponse(true, 'Found ' . count($results) . ' records', $results);
    }
    
} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>