<?php
// update_user.php - 更新管理員帳號／密碼／權限
// CORS 由 config.php 統一處理
require_once 'config.php';

try {
    $input        = json_decode(file_get_contents('php://input'), true);
    $username     = trim($input['username']     ?? '');
    $old_password = trim($input['old_password'] ?? '');
    $new_username = trim($input['new_username'] ?? '');
    $new_password = trim($input['new_password'] ?? '');

    // 新增三個欄位（選填，僅管理員操作其他帳號時使用）
    $target_objectId         = trim($input['target_objectId']          ?? '');
    $allocatable_count       = $input['allocatable_count']              ?? null;
    $can_allocate_infinity   = $input['can_allocate_infinity']          ?? null;
    $role                    = trim($input['role']                      ?? '');

    if (!$username || !$old_password) {
        sendResponse(false, '請提供目前帳號與密碼', null, 400);
    }

    $conn = getDBConnection();

    // ── 驗證操作者舊密碼 ──────────────────────────────────────
    $stmt = $conn->prepare('SELECT * FROM `User` WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($old_password, $user['password'])) {
        sendResponse(false, '目前密碼錯誤', null, 401);
    }

    // ── 決定要更新哪個帳號 ────────────────────────────────────
    // target_objectId 有填 → 管理員更新其他人（需 role=admin）
    // 沒填 → 更新自己
    if ($target_objectId && $target_objectId !== $user['objectId']) {
        if (($user['role'] ?? 'admin') !== 'admin') {
            sendResponse(false, '權限不足，只有管理員可修改其他帳號', null, 403);
        }
        $targetStmt = $conn->prepare('SELECT * FROM `User` WHERE objectId = ? LIMIT 1');
        $targetStmt->execute([$target_objectId]);
        $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) sendResponse(false, '找不到目標帳號', null, 404);
        $editObjectId = $target_objectId;
    } else {
        $targetUser   = $user;
        $editObjectId = $user['objectId'];
    }

    // ── 組合更新欄位 ──────────────────────────────────────────
    $fields = [];
    $params = [];

    if ($new_username && $new_username !== $targetUser['username']) {
        $chk = $conn->prepare('SELECT id FROM `User` WHERE username = ? LIMIT 1');
        $chk->execute([$new_username]);
        if ($chk->fetch()) sendResponse(false, '該帳號名稱已被使用', null, 409);
        $fields[] = 'username = :new_username';
        $params[':new_username'] = $new_username;
    }
    if ($new_password) {
        $fields[] = 'password = :new_password';
        $params[':new_password'] = password_hash($new_password, PASSWORD_BCRYPT);
    }
    if ($allocatable_count !== null) {
        $fields[] = 'allocatable_count = :allocatable_count';
        $params[':allocatable_count'] = max(0, (int)$allocatable_count);
    }
    if ($can_allocate_infinity !== null) {
        $fields[] = 'can_allocate_infinity = :can_allocate_infinity';
        $params[':can_allocate_infinity'] = ($can_allocate_infinity === true || $can_allocate_infinity === 1 || $can_allocate_infinity === '1') ? 1 : 0;
    }
    if ($role && in_array($role, ['admin', 'reseller'])) {
        $fields[] = 'role = :role';
        $params[':role'] = $role;
    }

    if (empty($fields)) {
        sendResponse(false, '未提供任何要更新的資料', null, 400);
    }

    $fields[]            = 'updated_at = NOW()';
    $params[':objectId'] = $editObjectId;

    $sql  = 'UPDATE `User` SET ' . implode(', ', $fields) . ' WHERE objectId = :objectId';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    // 回傳更新後的資料
    $fetchStmt = $conn->prepare('SELECT objectId, username, role, can_allocate_infinity, allocatable_count FROM `User` WHERE objectId = ? LIMIT 1');
    $fetchStmt->execute([$editObjectId]);
    $updated = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    $updated['can_allocate_infinity'] = (bool)$updated['can_allocate_infinity'];
    $updated['allocatable_count']     = (int)$updated['allocatable_count'];

    sendResponse(true, '帳號資料已更新', $updated);

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
