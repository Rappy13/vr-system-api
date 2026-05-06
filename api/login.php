<?php
// api/login.php
require_once __DIR__ . '/config.php';
// ↑ config.php 已處理好 headers、CORS、Content-Type，這裡不需要重複寫

// 取得 DB 連線（使用 config.php 的 getDBConnection()）
$pdo = getDBConnection();

$input    = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (!$username || !$password) {
    sendResponse(false, '請填寫帳號與密碼', null, 400);
}

$stmt = $pdo->prepare('SELECT * FROM `User` WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    sendResponse(false, '帳號或密碼錯誤', null, 401);
}

$token = base64_encode($user['objectId'] . ':' . time() . ':' . bin2hex(random_bytes(8)));

sendResponse(true, '登入成功', [
    'token'    => $token,
    'username' => $user['username'],
    'objectId' => $user['objectId'],
]);
