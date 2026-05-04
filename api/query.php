<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$BASE_URL = 'https://vr-system-api.onrender.com';

function apiGet($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return null;
    return json_decode($res, true);
}

function apiPost($url, $body) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return null;
    return json_decode($res, true);
}

// ── Input ────────────────────────────────────────────────────────────────────
$input       = json_decode(file_get_contents('php://input'), true);
$serial_code = trim($input['serial_code'] ?? '');
$date_filter = trim($input['date'] ?? '');   // YYYY-MM-DD

if (!$serial_code || !$date_filter) {
    echo json_encode(['success' => false, 'message' => '請填寫學員代碼與日期']);
    exit();
}

// ── Step 1: License → objectId ───────────────────────────────────────────────
$licenseData = apiGet("$BASE_URL/api/load_license.php?serial_code=" . urlencode($serial_code));
if (!$licenseData || empty($licenseData['data'])) {
    echo json_encode(['success' => false, 'message' => '找不到該學員代碼，請確認後重試']);
    exit();
}
$objectId = $licenseData['data']['objectId'] ?? ($licenseData['data'][0]['objectId'] ?? null);
if (!$objectId) {
    echo json_encode(['success' => false, 'message' => '無法取得學員 ObjectId']);
    exit();
}

// ── Step 2: VR Records by objectId ──────────────────────────────────────────
$recordData = apiGet("$BASE_URL/api/load_vr_record.php?player_id=" . urlencode($objectId));
if (!$recordData || empty($recordData['data'])) {
    echo json_encode(['success' => false, 'message' => '該學員在此日期查無訓練紀錄']);
    exit();
}

$allRecords = $recordData['data'];

// ── Step 3: Filter by date ───────────────────────────────────────────────────
$filtered = array_filter($allRecords, function($rec) use ($date_filter) {
    $recDate = substr($rec['created_at'] ?? $rec['fake_time'] ?? '', 0, 10);
    return $recDate === $date_filter;
});

if (empty($filtered)) {
    echo json_encode(['success' => false, 'message' => "在 $date_filter 查無訓練紀錄"]);
    exit();
}

// ── Step 4: For each record → analyze_details ───────────────────────────────
$results = [];
foreach ($filtered as $rec) {
    $pressRaw  = $rec['press_data'] ?? '{}';
    $pressObj  = is_string($pressRaw) ? json_decode($pressRaw, true) : $pressRaw;
    $dataItems = $pressObj['data'] ?? [];

    if (empty($dataItems)) continue;

    $gameData   = $dataItems[0];
    $sceneId    = (int)($rec['scene']  ?? 0);
    $feId       = (int)($gameData['FE_id'] ?? 0);
    $timeLimit  = (float)($gameData['FE_limit'] ?? 60.0);

    $analyzeBody = [
        'data'          => [$gameData],
        'id'            => $sceneId,
        'FireExtin_id'  => $feId,
        'TimeLimit'     => $timeLimit,
    ];

    $analyzed = apiPost("$BASE_URL/api/analyze_details.php", $analyzeBody);

    // ── Build result row ────────────────────────────────────────────────────
    $details = $analyzed['data'] ?? [];

    // Medal / rating
    $isPerfect = (int)($gameData['is_perfect'] ?? 0);
    $isSuccess = (int)($gameData['is_success'] ?? 0);
    $isSpWrong = (int)($gameData['is_sp_wrong'] ?? 0);
    $killTime  = round((float)($gameData['kill_fire_time'] ?? 0), 2);

    if (!$isSuccess) {
        $rating = '失敗';
    } elseif ($isPerfect === 3 || ($details['medal'] ?? '') === 'gold')   { $rating = '🥇 金牌'; }
    elseif ($isPerfect === 2 || ($details['medal'] ?? '') === 'silver') { $rating = '🥈 銀牌'; }
    elseif ($isPerfect === 1 || ($details['medal'] ?? '') === 'bronze') { $rating = '🥉 銅牌'; }
    else { $rating = $isSuccess ? '完成' : '失敗'; }

    // Scene name map
    $sceneMap = [0=>'未知', 1=>'場景A', 2=>'場景B', 3=>'場景C'];
    $sceneName = $sceneMap[$sceneId] ?? "場景$sceneId";

    // FE name map
    $feMap = [0=>'未選擇', 1=>'乾粉滅火器', 2=>'CO₂滅火器', 3=>'泡沫滅火器'];
    $feName = $feMap[$feId] ?? "滅火器$feId";

    $results[] = [
        'date'              => substr($rec['fake_time'] ?? $rec['created_at'] ?? '', 0, 10),
        'player_id'         => $rec['player_id'] ?? '',
        'scene'             => $sceneName,
        'count'             => (int)($gameData['press_count'] ?? 0),
        'select_fe'         => $feName,
        // from analyze_details
        'trial_in_20s'      => $details['trial_in_20s']        ?? ($analyzed['trial_in_20s']        ?? '-'),
        'trial_outside'     => $details['trial_outside']       ?? ($analyzed['trial_outside']       ?? '-'),
        'trial_distance'    => $details['trial_distance']      ?? ($analyzed['trial_distance']      ?? '-'),
        'fire_max_range'    => $details['fire_max_range']      ?? ($analyzed['fire_max_range']      ?? '-'),
        'fire_distance'     => $details['fire_distance']       ?? ($analyzed['fire_distance']       ?? '-'),
        'no_oil_splash'     => $details['no_oil_splash']       ?? ($analyzed['no_oil_splash']       ?? '-'),
        'no_intermittent'   => $details['no_intermittent']     ?? ($analyzed['no_intermittent']     ?? '-'),
        // from press_data
        'is_success'        => $isSuccess ? '✔ 成功' : '✘ 失敗',
        'kill_time'         => $killTime . 's',
        'sp_wrong'          => $isSpWrong ? '有' : '無',
        'other_details'     => $details['other_details']       ?? ($analyzed['other_details']       ?? '-'),
        'rating'            => $rating,
        // raw for debug
        '_analyzed_raw'     => $analyzed,
    ];
}

if (empty($results)) {
    echo json_encode(['success' => false, 'message' => '解析後無有效資料']);
    exit();
}

echo json_encode([
    'success'     => true,
    'serial_code' => $serial_code,
    'date'        => $date_filter,
    'objectId'    => $objectId,
    'total'       => count($results),
    'records'     => $results,
]);
