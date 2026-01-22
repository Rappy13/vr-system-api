<?php
// update_license.php - 更新 License 資料

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendResponse(false, 'Method not allowed. Use POST or PUT.', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(false, 'Invalid JSON data', null, 400);
    }
    
    // 必須提供 objectId 或 serial_code 作為更新依據
    $objectId = $input['objectId'] ?? null;
    $serialCode = $input['serial_code'] ?? null;
    
    if (!$objectId && !$serialCode) {
        sendResponse(false, 'Either objectId or serial_code is required for update', null, 400);
    }
    
    $conn = getDBConnection();
    
    // 先查詢記錄是否存在
    if ($objectId) {
        $checkSql = "SELECT * FROM License WHERE objectId = :identifier";
        $identifier = $objectId;
    } else {
        $checkSql = "SELECT * FROM License WHERE serial_code = :identifier";
        $identifier = $serialCode;
    }
    
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([':identifier' => $identifier]);
    $existingRecord = $checkStmt->fetch();
    
    if (!$existingRecord) {
        sendResponse(false, 'License not found', null, 404);
    }
    
    // 準備更新的欄位（只更新提供的欄位）
    $updateFields = [];
    $updateParams = [];
    
    // 可更新的欄位列表
    $allowedFields = [
        'active' => 'active',
        'count' => 'count',
        'email' => 'email',
        'infinity' => 'infinity',
        'parent' => 'parent',
        'serial_code' => 'serial_code',
        'user_name' => 'user_name',
        'stage_status' => 'stage_status'
    ];
    
    foreach ($allowedFields as $inputKey => $dbColumn) {
        if (array_key_exists($inputKey, $input)) {
            $updateFields[] = "{$dbColumn} = :{$inputKey}";
            
            // 特殊處理布林值
            if ($inputKey === 'active' || $inputKey === 'infinity') {
                $updateParams[":{$inputKey}"] = (bool)$input[$inputKey];
            } elseif ($inputKey === 'count') {
                $updateParams[":{$inputKey}"] = (int)$input[$inputKey];
            } else {
                $updateParams[":{$inputKey}"] = $input[$inputKey];
            }
        }
    }
    
    // 如果沒有要更新的欄位
    if (empty($updateFields)) {
        sendResponse(false, 'No fields to update', null, 400);
    }
    
    // 構建 SQL 更新語句
    if ($objectId) {
        $sql = "UPDATE License SET " . implode(', ', $updateFields) . " WHERE objectId = :identifier";
    } else {
        $sql = "UPDATE License SET " . implode(', ', $updateFields) . " WHERE serial_code = :identifier";
    }
    
    $updateParams[':identifier'] = $identifier;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($updateParams);
    
    if ($stmt->rowCount() > 0) {
        // 獲取更新後的完整記錄
        $fetchSql = $objectId 
            ? "SELECT * FROM License WHERE objectId = :identifier"
            : "SELECT * FROM License WHERE serial_code = :identifier";
        
        $fetchStmt = $conn->prepare($fetchSql);
        $fetchStmt->execute([':identifier' => $identifier]);
        $updatedRecord = $fetchStmt->fetch();
        
        // 轉換布林值
        $updatedRecord['active'] = (bool)$updatedRecord['active'];
        $updatedRecord['infinity'] = (bool)$updatedRecord['infinity'];
        $updatedRecord['count'] = (int)$updatedRecord['count'];
        
        sendResponse(true, 'License updated successfully', $updatedRecord);
    } else {
        // 沒有變更（可能是提供的值與現有值相同）
        sendResponse(true, 'No changes made (values are the same)', $existingRecord);
    }
    
} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>