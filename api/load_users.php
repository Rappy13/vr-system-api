<?php
// api/load_users.php - 讀取所有 User（除自己外），僅 admin 可用
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

    // 取得登入者資料
    $stmt = $pdo->prepare('SELECT objectId, role FROM `User` WHERE token = ? AND token IS NOT NULL LIMIT 1');
    $stmt->execute([$token]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$me) sendResponse(false, 'Unauthorized', null, 401);
    if ($me['role'] !== 'admin') sendResponse(false, '權限不足，僅管理員可存取', null, 403);

    // 讀取除自己以外的所有 User
    $stmt = $pdo->prepare(
        'SELECT objectId, username, role, can_allocate_infinity, allocatable_count
         FROM `User`
         WHERE objectId != ?
         ORDER BY role ASC, username ASC'
    );
    $stmt->execute([$me['objectId']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$u) {
        $u['can_allocate_infinity'] = (bool)$u['can_allocate_infinity'];
        $u['allocatable_count']     = (int)$u['allocatable_count'];
    }

    sendResponse(true, 'Found ' . count($users) . ' users', $users);

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
