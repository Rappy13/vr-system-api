<?php
// update_license.php - 更新 License 資料

require_once 'config.php';

// ── Token → objectId ──────────────────────────────────────────
function getCallerObjectId(PDO $conn): ?string {
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) return null;
    $stmt = $conn->prepare('SELECT objectId FROM `User` WHERE token = ? AND token IS NOT NULL LIMIT 1');
    $stmt->execute([trim($m[1])]);
    $row = $stmt->fetch();
    return $row ? $row['objectId'] : null;
}

// ── 取得 USER 的次數分配設定 ──────────────────────────────────
function getUserAllocInfo(PDO $conn, string $objectId): array {
    $stmt = $conn->prepare(
        'SELECT can_allocate_infinity, allocatable_count FROM `User` WHERE objectId = ? LIMIT 1'
    );
    $stmt->execute([$objectId]);
    $row = $stmt->fetch();
    return [
        'can_inf' => $row ? (bool)$row['can_allocate_infinity'] : false,
        'alloc'   => $row ? (int)$row['allocatable_count']      : 0,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendResponse(false, 'Method not allowed. Use POST or PUT.', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(false, 'Invalid JSON data', null, 400);
    }
    
    // 必須提供 objectId 或 serial_code 作為更新依據
    $objectId   = $input['objectId']    ?? null;
    $serialCode = $input['serial_code'] ?? null;
    
    if (!$objectId && !$serialCode) {
        sendResponse(false, 'Either objectId or serial_code is required for update', null, 400);
    }
    
    $conn = getDBConnection();
    
    // 先查詢記錄是否存在
    if ($objectId) {
        $checkSql   = "SELECT * FROM License WHERE objectId = :identifier";
        $identifier = $objectId;
    } else {
        $checkSql   = "SELECT * FROM License WHERE serial_code = :identifier";
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
        'active'       => 'active',
        'count'        => 'count',
        'email'        => 'email',
        'infinity'     => 'infinity',
        'parent'       => 'parent',
        'serial_code'  => 'serial_code',
        'user_name'    => 'user_name',
        'stage_status' => 'stage_status',
        'max_devices'  => 'max_devices',   // ← 裝置上限
    ];
    
    foreach ($allowedFields as $inputKey => $dbColumn) {
        if (array_key_exists($inputKey, $input)) {
            $updateFields[] = "{$dbColumn} = :{$inputKey}";
            
            if ($inputKey === 'active' || $inputKey === 'infinity') {
                $val = $input[$inputKey];
                $updateParams[":{$inputKey}"] = ($val === true || $val === 1 || $val === '1' || $val === 'true') ? 1 : 0;
            } elseif ($inputKey === 'count') {
                $val = $input[$inputKey];
                $updateParams[":{$inputKey}"] = ($val === null || $val === '') ? 0 : (int)$val;
            } elseif ($inputKey === 'max_devices') {
                $val = $input[$inputKey];
                // null 或空字串 → NULL（無限裝置），否則轉整數
                $updateParams[":{$inputKey}"] = ($val === null || $val === '') ? null : (int)$val;
            } else {
                $updateParams[":{$inputKey}"] = $input[$inputKey] ?? '';
            }
        }
    }
    
    // ── 差額次數處理 ─────────────────────────────────────────
    if (array_key_exists('count', $input) || array_key_exists('infinity', $input)) {
        $callerId = getCallerObjectId($conn);
        if ($callerId) {
            $userInfo = getUserAllocInfo($conn, $callerId);
            if (!$userInfo['can_inf']) {
                $newInfinity = isset($input['infinity'])
                    ? (int)(bool)$input['infinity']
                    : (int)$existingRecord['infinity'];
                $newCount = (!$newInfinity && isset($input['count']))
                    ? max(0, (int)$input['count']) : 0;
                $oldInfinity = (int)$existingRecord['infinity'];
                $oldUsed     = $oldInfinity ? 0 : (int)$existingRecord['count'];
                $diff        = $newCount - $oldUsed;
                if ($diff > 0 && $diff > $userInfo['alloc']) {
                    sendResponse(false, "次數超過可分配上限（剩餘 {$userInfo['alloc']} 次）", null, 422);
                }
                if ($diff !== 0) {
                    $conn->prepare(
                        'UPDATE `User` SET allocatable_count = allocatable_count - :diff WHERE objectId = :id'
                    )->execute([':diff' => $diff, ':id' => $callerId]);
                }
            }
        }
    }

    if (empty($updateFields)) {
        sendResponse(false, 'No fields to update', null, 400);
    }
    
    if ($objectId) {
        $sql = "UPDATE License SET " . implode(', ', $updateFields) . " WHERE objectId = :identifier";
    } else {
        $sql = "UPDATE License SET " . implode(', ', $updateFields) . " WHERE serial_code = :identifier";
    }
    
    $updateParams[':identifier'] = $identifier;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($updateParams);
    
    if ($stmt->rowCount() > 0) {
        $fetchSql = $objectId
            ? "SELECT * FROM License WHERE objectId = :identifier"
            : "SELECT * FROM License WHERE serial_code = :identifier";
        
        $fetchStmt = $conn->prepare($fetchSql);
        $fetchStmt->execute([':identifier' => $identifier]);
        $updatedRecord = $fetchStmt->fetch();
        
        $updatedRecord['active']      = (bool)$updatedRecord['active'];
        $updatedRecord['infinity']    = (bool)$updatedRecord['infinity'];
        $updatedRecord['count']       = (int)$updatedRecord['count'];
        $updatedRecord['max_devices'] = $updatedRecord['max_devices'] !== null
                                        ? (int)$updatedRecord['max_devices']
                                        : null;
        
        sendResponse(true, 'License updated successfully', $updatedRecord);
    } else {
        sendResponse(true, 'No changes made (values are the same)', $existingRecord);
    }
    
} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
