<?php
// api/validate_license.php - 序號驗證 + 裝置綁定檢查
// Unity POST: { "serial_code": "XXX", "device_id": "sha256hash", "platform": "Android" }
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $serial_code = trim($input['serial_code'] ?? '');
    $device_id   = trim($input['device_id']   ?? '');
    $platform    = trim($input['platform']     ?? 'Unknown');

    // --- 基本參數檢查 ---
    if (!$serial_code || !$device_id) {
        sendResponse(false, 'Missing serial_code or device_id', null, 400);
    }

    // device_id 長度保護（SHA256 = 64 chars）
    if (strlen($device_id) > 256) {
        sendResponse(false, 'Invalid device_id', null, 400);
    }

    $pdo = getDBConnection();

    // --- 1. 查詢序號是否存在且啟用 ---
    $stmt = $pdo->prepare(
        "SELECT serial_code, active, max_devices FROM License WHERE serial_code = :serial_code"
    );
    $stmt->execute([':serial_code' => $serial_code]);
    $license = $stmt->fetch();

    if (!$license) {
        sendResponse(false, '序號不存在', null, 404);
    }
    if (!(bool)$license['active']) {
        sendResponse(false, '序號已停用，請聯絡管理員', null, 403);
    }

    $max_devices = $license['max_devices']; // NULL = 無限制

    // --- 2. 查詢此裝置是否已綁定 ---
    $stmt = $pdo->prepare(
        "SELECT id FROM LicenseDevices
         WHERE serial_code = :serial_code AND device_id = :device_id"
    );
    $stmt->execute([
        ':serial_code' => $serial_code,
        ':device_id'   => $device_id,
    ]);
    $existing = $stmt->fetch();

    if ($existing) {
        // 已綁定過 → 更新 last_seen，直接通過
        $pdo->prepare(
            "UPDATE LicenseDevices
             SET last_seen = NOW(), platform = :platform
             WHERE serial_code = :serial_code AND device_id = :device_id"
        )->execute([
            ':platform'    => $platform,
            ':serial_code' => $serial_code,
            ':device_id'   => $device_id,
        ]);
        sendResponse(true, '驗證成功', ['device_status' => 'existing']);
    }

    // --- 3. 新裝置 → 檢查是否超過裝置上限 ---
    if ($max_devices !== null) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as cnt FROM LicenseDevices WHERE serial_code = :serial_code"
        );
        $stmt->execute([':serial_code' => $serial_code]);
        $current_count = (int)$stmt->fetch()['cnt'];

        if ($current_count >= (int)$max_devices) {
            sendResponse(false, "此序號已達裝置上限（{$max_devices} 台），請聯絡管理員解除綁定", null, 403);
        }
    }

    // --- 4. 寫入新裝置綁定 ---
    $pdo->prepare(
        "INSERT INTO LicenseDevices (serial_code, device_id, platform)
         VALUES (:serial_code, :device_id, :platform)"
    )->execute([
        ':serial_code' => $serial_code,
        ':device_id'   => $device_id,
        ':platform'    => $platform,
    ]);

    sendResponse(true, '驗證成功，新裝置已綁定', ['device_status' => 'new_binding']);

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
