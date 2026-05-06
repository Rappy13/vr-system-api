<?php
// api/auth_login.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$BASE = 'https://vr-system-api.onrender.com';

function apiGet($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : null;
}

function apiPost($url, $body) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : null;
}

$input    = json_decode(file_get_contents('php://input'), true);
$action   = $input['action'] ?? '';

// ── LOGIN ────────────────────────────────────────────────────────────────────
if ($action === 'login') {
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    if (!$username || !$password) {
        echo json_encode(['success' => false, 'message' => '請填寫帳號與密碼']);
        exit();
    }
    // Fetch user from remote API (or your own DB)
    // We store users in a "User" collection via the same Parse-style API if available,
    // otherwise we use a local hardcoded check as fallback.
    // Try fetching from API first:
    $userData = apiGet("$BASE/api/load_user.php?username=" . urlencode($username));
    if ($userData && $userData['success'] && !empty($userData['data'])) {
        $user = is_array($userData['data']) && isset($userData['data'][0])
              ? $userData['data'][0] : $userData['data'];
        if (password_verify($password, $user['password'] ?? '')) {
            echo json_encode([
                'success'  => true,
                'token'    => base64_encode($username . ':' . time()),
                'username' => $user['username'] ?? $username,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => '帳號或密碼錯誤']);
        }
    } else {
        // Fallback: hardcoded admin (remove after setting up DB)
        if ($username === 'admin' && $password === 'admin1234') {
            echo json_encode([
                'success'  => true,
                'token'    => base64_encode('admin:' . time()),
                'username' => 'admin',
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => '帳號或密碼錯誤']);
        }
    }
    exit();
}

// ── LICENSE LIST ─────────────────────────────────────────────────────────────
if ($action === 'list_licenses') {
    $data = apiGet("$BASE/api/load_license.php");
    echo json_encode($data ?? ['success' => false, 'message' => 'API 無回應']);
    exit();
}

// ── SAVE LICENSE (create / update) ───────────────────────────────────────────
if ($action === 'save_license') {
    $payload = $input['payload'] ?? [];
    $isEdit  = !empty($payload['objectId']);
    $endpoint = $isEdit ? "$BASE/api/update_license.php" : "$BASE/api/save_license.php";
    $result = apiPost($endpoint, $payload);
    echo json_encode($result ?? ['success' => false, 'message' => 'API 無回應']);
    exit();
}

// ── TOGGLE ACTIVE ─────────────────────────────────────────────────────────────
if ($action === 'toggle_license') {
    $objectId = $input['objectId'] ?? '';
    $active   = (bool)($input['active'] ?? false);
    $result   = apiPost("$BASE/api/update_license.php", [
        'objectId' => $objectId,
        'active'   => $active,
    ]);
    echo json_encode($result ?? ['success' => false, 'message' => 'API 無回應']);
    exit();
}

echo json_encode(['success' => false, 'message' => '未知操作']);
