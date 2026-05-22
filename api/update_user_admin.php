<?php
// api/update_user_admin.php - 管理員修改其他使用者資料（不需舊密碼）
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

function getBearerToken() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return trim($m[1]);
    return null;
}

try {
    $pdo   = getDBConnection();
    $token = getBearerToken();

    if (!$token) sendResponse(false, 'Unauthorized', null, 401);

    // 驗證操作者為 admin
    $stmt = $pdo->prepare('SELECT objectId, role FROM `User` WHERE token = ? AND token IS NOT NULL LIMIT 1');
    $stmt->execute([$token]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) sendResponse(false, 'Unauthorized', null, 401);
    if ($me['role'] !== 'admin') sendResponse(false, '權限不足，僅管理員可執行此操作', null, 403);

    $input           = json_decode(file_get_contents('php://input'), true) ?? [];
    $target_objectId = trim($input['target_objectId'] ?? '');
    $new_username    = trim($input['new_username']    ?? '');
    $new_password    = trim($input['new_password']    ?? '');
    $role            = trim($input['role']            ?? '');
    $allocatable_count     = $input['allocatable_count']     ?? null;
    $can_allocate_infinity = $input['can_allocate_infinity'] ?? null;

    if (!$target_objectId) sendResponse(false, 'Missing target_objectId', null, 400);

    // 不能修改自己（用 update_user.php）
    if ($target_objectId === $me['objectId']) {
        sendResponse(false, '請使用帳號設定修改自己的資料', null, 400);
    }

    // 確認目標 User 存在
    $stmt = $pdo->prepare('SELECT * FROM `User` WHERE objectId = ? LIMIT 1');
    $stmt->execute([$target_objectId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) sendResponse(false, '找不到目標使用者', null, 404);

    // 組合更新欄位
    $fields = [];
    $params = [];

    if ($new_username && $new_username !== $target['username']) {
        $chk = $pdo->prepare('SELECT id FROM `User` WHERE username = ? AND objectId != ? LIMIT 1');
        $chk->execute([$new_username, $target_objectId]);
        if ($chk->fetch()) sendResponse(false, '該帳號名稱已被使用', null, 409);
        $fields[] = 'username = :new_username';
        $params[':new_username'] = $new_username;
    }
    if ($new_password) {
        $fields[] = 'password = :new_password';
        $params[':new_password'] = password_hash($new_password, PASSWORD_BCRYPT);
    }
    if ($role && in_array($role, ['admin', 'reseller'])) {
        $fields[] = 'role = :role';
        $params[':role'] = $role;
    }
    if ($can_allocate_infinity !== null) {
        $fields[] = 'can_allocate_infinity = :can_inf';
        $params[':can_inf'] = ($can_allocate_infinity === true || $can_allocate_infinity == 1) ? 1 : 0;
    }
    if ($allocatable_count !== null) {
        $fields[] = 'allocatable_count = :alloc';
        $params[':alloc'] = max(0, (int)$allocatable_count);
    }

    if (empty($fields)) sendResponse(false, '未提供任何要更新的資料', null, 400);

    $fields[]            = 'updated_at = NOW()';
    $params[':objectId'] = $target_objectId;

    $sql  = 'UPDATE `User` SET ' . implode(', ', $fields) . ' WHERE objectId = :objectId';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 回傳更新後資料
    $fetchStmt = $pdo->prepare(
        'SELECT objectId, username, role, can_allocate_infinity, allocatable_count FROM `User` WHERE objectId = ? LIMIT 1'
    );
    $fetchStmt->execute([$target_objectId]);
    $updated = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    $updated['can_allocate_infinity'] = (bool)$updated['can_allocate_infinity'];
    $updated['allocatable_count']     = (int)$updated['allocatable_count'];

    sendResponse(true, '使用者資料已更新', $updated);

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
