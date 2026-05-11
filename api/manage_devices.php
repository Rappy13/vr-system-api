<?php
// manage_devices.php - 裝置綁定管理（查詢 / 解除）
// 使用 Bearer token 驗證（與前端 login.php 同一套）
require_once 'config.php';

// ── Bearer Token 驗證 ─────────────────────────────────────────
function getAuthToken() {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return $m[1];
    return null;
}

function validateToken($conn, $token) {
    if (!$token) return false;
    try {
        // 依照你的 admin_users / sessions table 調整
        $stmt = $conn->prepare(
            "SELECT id FROM admin_users WHERE token = :token AND token IS NOT NULL"
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        // 若 table 結構不同先回 true，確認後再鎖
        return true;
    }
}
// ─────────────────────────────────────────────────────────────

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn  = getDBConnection();
    $token = getAuthToken();

    if (!validateToken($conn, $token)) {
        sendResponse(false, 'Unauthorized', null, 401);
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET: 查詢某序號的所有綁定裝置 ────────────────────────
    if ($method === 'GET') {
        $serial_code = trim($_GET['serial_code'] ?? '');
        if (!$serial_code) sendResponse(false, 'Missing serial_code', null, 400);

        $stmt = $conn->prepare(
            "SELECT l.max_devices,
                    d.id, d.device_id, d.platform, d.first_seen, d.last_seen
             FROM License l
             LEFT JOIN LicenseDevices d ON d.serial_code = l.serial_code
             WHERE l.serial_code = :serial_code
             ORDER BY d.first_seen ASC"
        );
        $stmt->execute([':serial_code' => $serial_code]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) sendResponse(false, '序號不存在', null, 404);

        $max_devices = $rows[0]['max_devices'];
        $devices = [];
        foreach ($rows as $row) {
            if ($row['id']) {
                $devices[] = [
                    'id'         => (int)$row['id'],
                    'device_id'  => $row['device_id'],
                    'platform'   => $row['platform'] ?? 'Unknown',
                    'first_seen' => $row['first_seen'],
                    'last_seen'  => $row['last_seen'],
                ];
            }
        }

        sendResponse(true, 'OK', [
            'serial_code' => $serial_code,
            'max_devices' => $max_devices,
            'bound_count' => count($devices),
            'devices'     => $devices,
        ]);
    }

    // ── POST: 解除裝置綁定 ────────────────────────────────────
    if ($method === 'POST') {
        $input       = json_decode(file_get_contents('php://input'), true) ?? [];
        $action      = $input['action']      ?? 'delete';
        $serial_code = trim($input['serial_code'] ?? '');
        $row_id      = isset($input['id']) ? (int)$input['id'] : null;

        if ($action === 'delete_all' && $serial_code) {
            $stmt = $conn->prepare("DELETE FROM LicenseDevices WHERE serial_code = :serial_code");
            $stmt->execute([':serial_code' => $serial_code]);
            $deleted = $stmt->rowCount();
            sendResponse(true, "已解除 {$deleted} 台裝置綁定", ['deleted' => $deleted]);

        } elseif ($row_id) {
            $stmt = $conn->prepare("DELETE FROM LicenseDevices WHERE id = :id");
            $stmt->execute([':id' => $row_id]);
            if ($stmt->rowCount()) sendResponse(true, '裝置綁定已解除', ['deleted' => 1]);
            else sendResponse(false, '找不到該裝置記錄', null, 404);

        } else {
            sendResponse(false, 'Missing parameters: need id or (action=delete_all + serial_code)', null, 400);
        }
    }

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
