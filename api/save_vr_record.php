<?php
// save_vr_record.php - 儲存VR_Player_Record資料

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(false, 'Invalid JSON data', null, 400);
    }
    
    $conn = getDBConnection();
    
    // 如果沒有提供objectId，生成新的
    if (empty($input['objectId'])) {
        do {
            $objectId = generateObjectId();
        } while (!isObjectIdUnique($conn, 'VR_Player_Record', $objectId));
        $input['objectId'] = $objectId;
        $isNew = true;
    } else {
        $objectId = $input['objectId'];
        $isNew = false;
    }
    
    // 準備資料
    $age = isset($input['age']) ? (int)$input['age'] : null;
    $fake_time = $input['fake_time'] ?? null;
    $player_id = $input['player_id'] ?? null;
    $press_data = $input['press_data'] ?? null;
    $scene = isset($input['scene']) ? (int)$input['scene'] : null;
    $sexual = isset($input['sexual']) ? (int)$input['sexual'] : null;
    $user = $input['user'] ?? null;
    
    // 如果fake_time是字串，轉換為MySQL DATETIME格式
    if ($fake_time && !empty($fake_time)) {
        $date = new DateTime($fake_time);
        $fake_time = $date->format('Y-m-d H:i:s');
    }
    
    if ($isNew) {
        // 新增記錄
        $sql = "INSERT INTO VR_Player_Record 
                (objectId, age, fake_time, player_id, press_data, scene, sexual, user) 
                VALUES 
                (:objectId, :age, :fake_time, :player_id, :press_data, :scene, :sexual, :user)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':objectId' => $objectId,
            ':age' => $age,
            ':fake_time' => $fake_time,
            ':player_id' => $player_id,
            ':press_data' => $press_data,
            ':scene' => $scene,
            ':sexual' => $sexual,
            ':user' => $user
        ]);
        
        sendResponse(true, 'VR Player Record created successfully', ['objectId' => $objectId], 201);
    } else {
        // 更新記錄
        $sql = "UPDATE VR_Player_Record SET 
                age = :age,
                fake_time = :fake_time,
                player_id = :player_id,
                press_data = :press_data,
                scene = :scene,
                sexual = :sexual,
                user = :user
                WHERE objectId = :objectId";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':objectId' => $objectId,
            ':age' => $age,
            ':fake_time' => $fake_time,
            ':player_id' => $player_id,
            ':press_data' => $press_data,
            ':scene' => $scene,
            ':sexual' => $sexual,
            ':user' => $user
        ]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(true, 'VR Player Record updated successfully', ['objectId' => $objectId]);
        } else {
            sendResponse(false, 'VR Player Record not found or no changes made', null, 404);
        }
    }
    
} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>