<?php
// api/login.php
require_once __DIR__ . '/config.php';

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

// 產生 token 並存入資料庫
$token = base64_encode($user['objectId'] . ':' . time() . ':' . bin2hex(random_bytes(8)));

$pdo->prepare('UPDATE `User` SET token = ? WHERE objectId = ?')
    ->execute([$token, $user['objectId']]);

sendResponse(true, '登入成功', [
    'token'    => $token,
    'username' => $user['username'],
    'objectId' => $user['objectId'],
]);
?>
