<?php
// load_license.php - 讀取License資料
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// ── 從 Bearer Token 取得登入者 objectId ──────────────────────
function getObjectIdFromToken(PDO $conn): ?string {
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) return null;
    $token = trim($m[1]);
    $stmt  = $conn->prepare('SELECT objectId FROM `User` WHERE token = ? AND token IS NOT NULL LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ? $row['objectId'] : null;
}

// ── 型別轉換 helper ───────────────────────────────────────────
function castLicense(array &$r): void {
    $r['active']   = (bool)$r['active'];
    $r['infinity'] = (bool)$r['infinity'];
    $r['count']    = (int)$r['count'];
    $r['max_devices'] = $r['max_devices'] !== null ? (int)$r['max_devices'] : null;
}

try {
    $conn = getDBConnection();

    // 支援 GET 和 POST 兩種方式
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $objectId    = $_GET['objectId']    ?? null;
        $serial_code = $_GET['serial_code'] ?? null;
        $user_name   = $_GET['user_name']   ?? null;
        $email       = $_GET['email']       ?? null;
    } else {
        $input       = json_decode(file_get_contents('php://input'), true);
        $objectId    = $input['objectId']    ?? null;
        $serial_code = $input['serial_code'] ?? null;
        $user_name   = $input['user_name']   ?? null;
        $email       = $input['email']       ?? null;
    }

    // 根據不同條件查詢
    if ($objectId) {
        $sql  = "SELECT * FROM License WHERE objectId = :objectId";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':objectId' => $objectId]);
        $result = $stmt->fetch();

        if ($result) {
            castLicense($result);
            sendResponse(true, 'License found', $result);
        } else {
            sendResponse(false, 'License not found', null, 404);
        }

    } elseif ($serial_code) {
        $sql  = "SELECT * FROM License WHERE serial_code = :serial_code";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':serial_code' => $serial_code]);
        $result = $stmt->fetch();

        if ($result) {
            castLicense($result);
            sendResponse(true, 'License found', $result);
        } else {
            sendResponse(false, 'License not found', null, 404);
        }

    } elseif ($user_name) {
        $sql  = "SELECT * FROM License WHERE user_name = :user_name";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user_name' => $user_name]);
        $results = $stmt->fetchAll();

        foreach ($results as &$r) castLicense($r);
        sendResponse(true, 'Found ' . count($results) . ' licenses', $results);

    } elseif ($email) {
        $sql  = "SELECT * FROM License WHERE email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $results = $stmt->fetchAll();

        if (empty($results)) sendResponse(false, 'License not found', null, 404);

        foreach ($results as &$r) castLicense($r);
        sendResponse(true, 'Found ' . count($results) . ' licenses', $results);

    } else {
        // ── 查詢全部：依登入者 objectId 篩選 parent ──────────
        $callerObjectId = getObjectIdFromToken($conn);
        if (!$callerObjectId) {
            sendResponse(false, 'Unauthorized', null, 401);
        }

        $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $sql  = "SELECT * FROM License WHERE parent = :parent LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':parent', $callerObjectId, PDO::PARAM_STR);
        $stmt->bindValue(':limit',  $limit,          PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,         PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll();

        foreach ($results as &$r) castLicense($r);
        sendResponse(true, 'Found ' . count($results) . ' licenses', $results);
    }

} catch(PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
