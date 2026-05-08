<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once 'config.php';

try {
    $input        = json_decode(file_get_contents('php://input'), true);
    $username     = trim($input['username']     ?? '');
    $old_password = trim($input['old_password'] ?? '');
    $new_username = trim($input['new_username'] ?? '');
    $new_password = trim($input['new_password'] ?? '');

    if (!$username || !$old_password) {
        sendResponse(false, '請提供目前帳號與密碼', null, 400);
    }

    $conn = getDBConnection();

    // ── 驗證舊密碼 ────────────────────────────────────────────────────────────
    $stmt = $conn->prepare('SELECT * FROM `User` WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($old_password, $user['password'])) {
        sendResponse(false, '目前密碼錯誤', null, 401);
    }

    // ── 確認有東西要更新 ──────────────────────────────────────────────────────
    if (!$new_username && !$new_password) {
        sendResponse(false, '未提供任何要更新的資料', null, 400);
    }

    // ── 若要改帳號，檢查是否已被使用 ─────────────────────────────────────────
    if ($new_username && $new_username !== $username) {
        $chk = $conn->prepare('SELECT id FROM `User` WHERE username = ? LIMIT 1');
        $chk->execute([$new_username]);
        if ($chk->fetch()) {
            sendResponse(false, '該帳號名稱已被使用', null, 409);
        }
    }

    // ── 組合更新欄位 ──────────────────────────────────────────────────────────
    $fields = [];
    $params = [];

    if ($new_username && $new_username !== $username) {
        $fields[] = 'username = :new_username';
        $params[':new_username'] = $new_username;
    }
    if ($new_password) {
        $fields[] = 'password = :new_password';
        $params[':new_password'] = password_hash($new_password, PASSWORD_BCRYPT);
    }
    $fields[]              = 'updated_at = NOW()';
    $params[':objectId']   = $user['objectId'];

    $sql  = 'UPDATE `User` SET ' . implode(', ', $fields) . ' WHERE objectId = :objectId';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    sendResponse(true, '帳號資料已更新', [
        'username' => $new_username ?: $username,
    ]);

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
