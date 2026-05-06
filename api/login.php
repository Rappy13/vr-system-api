<?php
// api/login.php — 部署到 onrender 的 /api/ 資料夾
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// ── DB 設定（請改成你的 MySQL 連線資訊）─────────────────────────────────────
$DB_HOST = 'localhost';
$DB_NAME = 'your_database';
$DB_USER = 'your_user';
$DB_PASS = 'your_password';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB 連線失敗: ' . $e->getMessage()]);
    exit();
}

$input    = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (!$username || !$password) {
    echo json_encode(['success' => false, 'message' => '請填寫帳號與密碼']);
    exit();
}

$stmt = $pdo->prepare('SELECT * FROM `User` WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => '帳號或密碼錯誤']);
    exit();
}

$token = base64_encode($user['objectId'] . ':' . time() . ':' . bin2hex(random_bytes(8)));

echo json_encode([
    'success'  => true,
    'token'    => $token,
    'username' => $user['username'],
    'objectId' => $user['objectId'],
]);
