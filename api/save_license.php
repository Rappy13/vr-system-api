<?php
// save_license.php - 新增/更新 License

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendResponse(false, 'Invalid JSON data', null, 400);
    }

    $conn = getDBConnection();

    // 準備欄位資料
    $active       = isset($input['active'])      ? (int)$input['active']    : 1;
    $count        = isset($input['count'])       ? (int)$input['count']     : 0;
    $email        = $input['email']      ?? null;
    $tel          = $input['tel']        ?? null;
    $infinity     = isset($input['infinity'])    ? (int)$input['infinity']  : 0;
    $parent       = $input['parent']     ?? null;
    $serial_code  = $input['serial_code'] ?? null;
    $user_name    = $input['user_name']  ?? null;
    $stage_status = isset($input['stage_status'])
                        ? (is_array($input['stage_status'])
                            ? json_encode($input['stage_status'])
                            : $input['stage_status'])
                        : null;
    $expiry_date  = null;
    if (!empty($input['expiry_date'])) {
        $expiry_date = (new DateTime($input['expiry_date']))->format('Y-m-d H:i:s');
    }

    // ── 新增 ──────────────────────────────────────────────
    if (empty($input['objectId'])) {

        do {
            $objectId = generateObjectId();
        } while (!isObjectIdUnique($conn, 'License', $objectId));

        // serial_code 若前端沒給，自動產生
        if (empty($serial_code)) {
            $serial_code = strtoupper(bin2hex(random_bytes(8)));
        }

        $sql = "INSERT INTO License
                    (objectId, active, count, email, tel, infinity,
                     parent, serial_code, user_name, stage_status, expiry_date)
                VALUES
                    (:objectId, :active, :count, :email, :tel, :infinity,
                     :parent, :serial_code, :user_name, :stage_status, :expiry_date)";

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
        ]);

        sendResponse(true, 'License created successfully', [
            'objectId'    => $objectId,
            'serial_code' => $serial_code,
        ], 201);

    // ── 更新 ──────────────────────────────────────────────
    } else {

        $sql = "UPDATE License SET
                    active       = :active,
                    count        = :count,
                    email        = :email,
                    tel          = :tel,
                    infinity     = :infinity,
                    parent       = :parent,
                    serial_code  = :serial_code,
                    user_name    = :user_name,
                    stage_status = :stage_status,
                    expiry_date  = :expiry_date
                WHERE objectId   = :objectId";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':objectId'     => $input['objectId'],
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
        ]);

        if ($stmt->rowCount() > 0) {
            sendResponse(true, 'License updated successfully', ['objectId' => $input['objectId']]);
        } else {
            sendResponse(false, 'License not found or no changes made', null, 404);
        }
    }

} catch (PDOException $e) {
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
