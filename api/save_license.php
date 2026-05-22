<?php
// save_license.php - 新增 / 更新 License

require_once 'config.php';

// ── 只接受 POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// ── 從 Bearer Token 取得目前登入 USER 的 objectId ────────────────────────────
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

// ── 產生 serial_code（8 碼小寫英數，去掉易混淆字符）────────────────────────
function generateSerialCode(int $length = 8): string {
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789'; // 去掉 0,1,i,l,o
    $code  = '';
    $max   = strlen($chars) - 1;
    while (strlen($code) < $length) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}

// ── 確保 serial_code 唯一 ────────────────────────────────────────────────────
function generateUniqueSerialCode(PDO $conn): string {
    do {
        $code = generateSerialCode();
        $stmt = $conn->prepare('SELECT COUNT(*) FROM License WHERE serial_code = :code');
        $stmt->execute([':code' => $code]);
    } while ($stmt->fetchColumn() > 0);
    return $code;
}

// ── 解析 request body ────────────────────────────────────────────────────────
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    sendResponse(false, 'Invalid JSON data', null, 400);
}

// ── 欄位驗證 helper ──────────────────────────────────────────────────────────
function requireField(array $input, string $field, string $label): void {
    if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
        sendResponse(false, "{$label} 為必填欄位", null, 422);
    }
}

// user_name 為必填
requireField($input, 'user_name', '單位名稱');

// ── 整理欄位 ─────────────────────────────────────────────────────────────────
$active      = isset($input['active'])   ? (int)(bool)$input['active']   : 1;
$infinity    = isset($input['infinity']) ? (int)(bool)$input['infinity'] : 0;
$count       = $infinity ? 0 : max(0, (int)($input['count'] ?? 0));
$email       = trim($input['email']     ?? '') ?: null;
$tel         = trim($input['tel']       ?? '') ?: null;
$serial_code = trim($input['serial_code'] ?? '') ?: null;
$user_name   = trim($input['user_name']);
$max_devices = isset($input['max_devices']) && $input['max_devices'] !== '' && $input['max_devices'] !== null
               ? (int)$input['max_devices'] : null;

// email 格式驗證（選填但有值時才驗）
if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Email 格式不正確', null, 422);
}

// stage_status 轉 JSON 字串
$stage_status = null;
if (isset($input['stage_status'])) {
    $stage_status = is_array($input['stage_status'])
        ? json_encode($input['stage_status'], JSON_UNESCAPED_UNICODE)
        : (string)$input['stage_status'];
}

// expiry_date 格式化
$expiry_date = null;
if (!empty($input['expiry_date'])) {
    try {
        $expiry_date = (new DateTime($input['expiry_date']))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        sendResponse(false, 'expiry_date 日期格式不正確', null, 422);
    }
}

// ── DB 連線 ──────────────────────────────────────────────────────────────────
try {
    $conn = getDBConnection();

    // ── 從 token 取得目前登入者的 objectId 作為 parent ──────────────────────
    $parent = getObjectIdFromToken($conn);
    if (!$parent) {
        sendResponse(false, 'Unauthorized：無法識別登入使用者', null, 401);
    }

    // ── 新增 ─────────────────────────────────────────────────────────────────
    if (empty($input['objectId'])) {

        // 產生唯一 objectId
        do {
            $objectId = generateObjectId();
        } while (!isObjectIdUnique($conn, 'License', $objectId));

        // 產生唯一 serial_code（前端若有傳則直接用，但仍檢查唯一性）
        if ($serial_code === null) {
            $serial_code = generateUniqueSerialCode($conn);
        } else {
            $check = $conn->prepare('SELECT COUNT(*) FROM License WHERE serial_code = :code');
            $check->execute([':code' => $serial_code]);
            if ($check->fetchColumn() > 0) {
                sendResponse(false, '授權碼已存在，請重新輸入', null, 409);
            }
        }

        $sql = "INSERT INTO License
                    (objectId, active, count, email, tel, infinity,
                     parent, serial_code, user_name, stage_status, expiry_date, max_devices)
                VALUES
                    (:objectId, :active, :count, :email, :tel, :infinity,
                     :parent, :serial_code, :user_name, :stage_status, :expiry_date, :max_devices)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':objectId'     => $objectId,
            ':active'       => $active,
            ':count'        => $count,
            ':email'        => $email,
            ':tel'          => $tel,
            ':infinity'     => $infinity,
            ':parent'       => $parent,
            ':serial_code'  => $serial_code,
            ':user_name'    => $user_name,
            ':stage_status' => $stage_status,
            ':expiry_date'  => $expiry_date,
            ':max_devices'  => $max_devices,
        ]);

        sendResponse(true, 'License 建立成功', [
            'objectId'    => $objectId,
            'serial_code' => $serial_code,
            'parent'      => $parent,
        ], 201);

    // ── 更新 ─────────────────────────────────────────────────────────────────
    } else {

        $objectId = trim($input['objectId']);

        // 確認該筆存在
        $exists = $conn->prepare('SELECT COUNT(*) FROM License WHERE objectId = :id');
        $exists->execute([':id' => $objectId]);
        if ($exists->fetchColumn() == 0) {
            sendResponse(false, 'License 不存在', null, 404);
        }

        // 若要更新 serial_code，檢查是否已被其他筆使用
        if ($serial_code !== null) {
            $dup = $conn->prepare(
                'SELECT COUNT(*) FROM License WHERE serial_code = :code AND objectId != :id'
            );
            $dup->execute([':code' => $serial_code, ':id' => $objectId]);
            if ($dup->fetchColumn() > 0) {
                sendResponse(false, '授權碼已被其他 License 使用', null, 409);
            }
        }

        $sql = "UPDATE License SET
                    active       = :active,
                    count        = :count,
                    email        = :email,
                    tel          = :tel,
                    infinity     = :infinity,
                    parent       = :parent,
                    user_name    = :user_name,
                    stage_status = :stage_status,
                    expiry_date  = :expiry_date,
                    max_devices  = :max_devices
                    " . ($serial_code !== null ? ", serial_code = :serial_code" : "") . "
                WHERE objectId = :objectId";

        $params = [
            ':objectId'     => $objectId,
            ':active'       => $active,
            ':count'        => $count,
            ':email'        => $email,
            ':tel'          => $tel,
            ':infinity'     => $infinity,
            ':parent'       => $parent,
            ':user_name'    => $user_name,
            ':stage_status' => $stage_status,
            ':expiry_date'  => $expiry_date,
            ':max_devices'  => $max_devices,
        ];
        if ($serial_code !== null) {
            $params[':serial_code'] = $serial_code;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        sendResponse(true, 'License 更新成功', [
            'objectId' => $objectId,
            'parent'   => $parent,
        ]);
    }

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
