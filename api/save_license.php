<?php
// save_license.php - 儲存License資料

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
        } while (!isObjectIdUnique($conn, 'License', $objectId));
        $input['objectId'] = $objectId;
        $isNew = true;
    } else {
        $objectId = $input['objectId'];
        $isNew = false;
    }
    
    // 準備資料
    $active = isset($input['active']) ? (bool)$input['active'] : false;
    $count = isset($input['count']) ? (int)$input['count'] : 0;
    $email = $input['email'] ?? null;
    $infinity = isset($input['infinity']) ? (bool)$input['infinity'] : false;
    $parent = $input['parent'] ?? null;
    $serial_code = $input['serial_code'] ?? null;
    $user_name = $input['user_name'] ?? null;
    $stage_status = $input['stage_status'] ?? null;
    
    if ($isNew) {
        // 新增記錄
        $sql = "INSERT INTO License 
                (objectId, active, count, email, infinity, parent, serial_code, user_name, stage_status) 
                VALUES 
                (:objectId, :active, :count, :email, :infinity, :parent, :serial_code, :user_name, :stage_status)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':objectId' => $objectId,
            ':active' => $active,
            ':count' => $count,
            ':email' => $email,
            ':infinity' => $infinity,
            ':parent' => $parent,
            ':serial_code' => $serial_code,
            ':user_name' => $user_name,
            ':stage_status' => $stage_status
        ]);
        
        sendResponse(true, 'License created successfully', ['objectId' => $objectId], 201);
    } else {
        // 更新記錄
        $sql = "UPDATE License SET 
                active = :active,
                count = :count,
                email = :email,
                infinity = :infinity,
                parent = :parent,
                serial_code = :serial_code,
                user_name = :user_name,
                stage_status = :stage_status
                WHERE objectId = :objectId";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':objectId' => $objectId,
            ':active' => $active,
            ':count' => $count,
            ':email' => $email,
            ':infinity' => $infinity,
            ':parent' => $parent,
            ':serial_code' => $serial_code,
            ':user_name' => $user_name,
            ':stage_status' => $stage_status
        ]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(true, 'License updated successfully', ['objectId' => $objectId]);
        } else {
            sendResponse(false, 'License not found or no changes made', null, 404);
        }
    }
    
} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>