<?php
// api/create_user.php - 管理員建立新使用者
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

    if (!$me)                  sendResponse(false, 'Unauthorized', null, 401);
    if ($me['role'] !== 'admin') sendResponse(false, '權限不足，僅管理員可建立使用者', null, 403);

    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $role     = trim($input['role']     ?? 'reseller');
    $can_inf  = isset($input['can_allocate_infinity'])
                ? (($input['can_allocate_infinity'] === true || $input['can_allocate_infinity'] == 1) ? 1 : 0)
                : 0;
    $alloc    = isset($input['allocatable_count']) ? max(0, (int)$input['allocatable_count']) : 0;

    // 必填檢查
    if (!$username) sendResponse(false, '帳號為必填', null, 422);
    if (!$password) sendResponse(false, '密碼為必填', null, 422);
    if (strlen($password) < 6) sendResponse(false, '密碼至少需要 6 個字元', null, 422);
    if (!in_array($role, ['admin', 'reseller'])) sendResponse(false, '階級值不正確', null, 422);

    // 帳號唯一性檢查
    $chk = $pdo->prepare('SELECT id FROM `User` WHERE username = ? LIMIT 1');
    $chk->execute([$username]);
    if ($chk->fetch()) sendResponse(false, '該帳號名稱已被使用', null, 409);

    // 產生唯一 objectId
    do {
        $objectId = generateObjectId();
        $dup = $pdo->prepare('SELECT id FROM `User` WHERE objectId = ? LIMIT 1');
        $dup->execute([$objectId]);
    } while ($dup->fetch());

    $hashed = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare(
        'INSERT INTO `User`
            (objectId, username, password, role, can_allocate_infinity, allocatable_count, created_at, updated_at)
         VALUES
            (:objectId, :username, :password, :role, :can_inf, :alloc, NOW(), NOW())'
    );
    $stmt->execute([
        ':objectId' => $objectId,
        ':username' => $username,
        ':password' => $hashed,
        ':role'     => $role,
        ':can_inf'  => $can_inf,
        ':alloc'    => $alloc,
    ]);

    sendResponse(true, '使用者建立成功', [
        'objectId'              => $objectId,
        'username'              => $username,
        'role'                  => $role,
        'can_allocate_infinity' => (bool)$can_inf,
        'allocatable_count'     => $alloc,
    ], 201);

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
