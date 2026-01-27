<?php
// analyze_details.php - 分析遊戲詳細結果（通過/不通過判斷）

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['data'])) {
        sendResponse(false, 'Invalid JSON data', null, 400);
    }
    
    $gameData = $input['data'][0];
    $id = $input['id'] ?? null;
    $FireExtin_id = $input['FireExtin_id'] ?? 0;
    $TimeLimit = $input['TimeLimit'] ?? 0;
    
    if ($id === null) {
        sendResponse(false, 'Missing required field: id', null, 400);
    }
    
    $pressData = $gameData['press_data'] ?? [];
    $pressCount = count($pressData);
    $isSuccess = $gameData['is_success'] ?? 0;
    $outfire = $gameData['is_outfire'] ?? 0;
    $sp_wrong = $gameData['is_sp_wrong'] ?? 0;
    $kill_fire_time = $gameData['kill_fire_time'] ?? 0;
    
    $result = analyzeDetails($id, $FireExtin_id, $TimeLimit, $pressData, $pressCount, $isSuccess, $outfire, $sp_wrong, $kill_fire_time);
    
    sendResponse(true, 'Analysis completed', $result);
    
} catch(Exception $e) {
    error_log("Analyze Details Error: " . $e->getMessage());
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}

function analyzeDetails($id, $FireExtin_id, $TimeLimit, $pressData, $pressCount, $isSuccess, $outfire, $sp_wrong, $kill_fire_time) {
    
    $results = [
        'check_start_time' => ['pass' => false, 'text' => '不通過', 'color' => 'red', 'value' => ''],
        'check_first_distance' => ['pass' => false, 'text' => '不通過', 'color' => 'red', 'value' => '0'],
        'check_second_distance' => ['pass' => false, 'text' => '不通過', 'color' => 'red', 'value' => '0'],
        'check_no_outfire' => ['pass' => false, 'text' => '不通過', 'color' => 'red'],
        'check_press_count' => ['pass' => false, 'text' => '不通過', 'color' => 'red'],
        'check_success' => ['pass' => false, 'text' => '不通過', 'color' => 'red'],
        'final_result' => ['pass' => false, 'text' => '由教官說明', 'color' => 'red'],
        'kill_time' => number_format($kill_fire_time, 2) . '秒',
        'special_error' => ['text' => '無', 'color' => 'white'],
        'is_perfect' => 0
    ];
    
    $count = 0;
    
    if ($pressCount > 1) {
        if ($pressData[0]['Continue_Time'] > 1.5) {
            // 第一次按壓時間過長
            $results['check_first_distance']['value'] = number_format($pressData[0]['Distance'], 2);
            $results['check_second_distance'] = checkSecondDistance($FireExtin_id, $pressData[0]['Distance']);
        } else {
            // 正常流程
            // 檢查開始時間
            if ($pressData[0]['Start_Time'] <= 20) {
                $results['check_start_time'] = ['pass' => true, 'text' => '通過', 'color' => 'white'];
                $count++;
            }
            
            // 檢查第一次距離
            if ($pressData[0]['Distance'] > 6) {
                $results['check_first_distance'] = ['pass' => true, 'text' => '通過', 'color' => 'white', 'value' => number_format($pressData[0]['Distance'], 2)];
                $count++;
            } else {
                $results['check_first_distance']['value'] = number_format($pressData[0]['Distance'], 2);
            }
            
            // 檢查第二次距離
            $secondCheck = checkSecondDistanceNormal($FireExtin_id, $pressData[1]['Distance']);
            $results['check_second_distance'] = $secondCheck;
            if ($secondCheck['pass']) $count++;
        }
        
        // 檢查是否滅火
        if (!$outfire) {
            $results['check_no_outfire'] = ['pass' => true, 'text' => '通過', 'color' => 'white'];
            $count++;
        }
        
        // 檢查按壓次數
        if ($pressCount <= 2) {
            $results['check_press_count'] = ['pass' => true, 'text' => '通過', 'color' => 'white'];
            $count++;
        }
        
    } elseif ($pressCount == 1) {
        $results['check_second_distance'] = checkSecondDistance($FireExtin_id, $pressData[0]['Distance']);
        $results['check_second_distance']['value'] = number_format($pressData[0]['Distance'], 2);
        
        if (!$outfire) {
            $results['check_no_outfire'] = ['pass' => true, 'text' => '通過', 'color' => 'white'];
        }
        
        $results['check_press_count'] = ['pass' => true, 'text' => '通過', 'color' => 'white'];
    }
    // pressCount == 0 時所有項目保持初始值（不通過）
    
    // 檢查成功
    if ($isSuccess == 1) {
        $results['check_success'] = ['pass' => true, 'text' => '通過', 'color' => 'white'];
        $count++;
    }
    
    // 判斷最終結果
    $requiredCount = 6;
    if ($id >= 5 && $id <= 8) {
        $requiredCount = 3;
    } elseif ($id > 8) {
        $requiredCount = 5;
    }
    
    if ($count >= $requiredCount && $TimeLimit > 0 && !$sp_wrong) {
        $results['final_result'] = ['pass' => true, 'text' => '通過', 'color' => 'white'];
        $results['is_perfect'] = 1;
    }
    
    // 特殊錯誤判斷
    if ($sp_wrong) {
        $errorMessages = [
            1 => '先打上方油盤',
            4 => '發生回火',
            8 => '問答題選錯',
            10 => '先打錯誤火源',
            13 => '先打錯誤火源',
            14 => '發生回火'
        ];
        
        $results['special_error'] = [
            'text' => $errorMessages[$id] ?? '特殊錯誤',
            'color' => 'red'
        ];
    }
    
    // 特殊場景提示
    if ($id == 14 && $FireExtin_id == 0) {
        $results['final_result']['text'] = '乾粉容易出現爆炸&火花';
        $results['final_result']['color'] = 'red';
    }
    
    // 場景 5-8 隱藏部分項目
    if ($id >= 5 && $id <= 8) {
        $results['hide_items'] = [1, 2, 3, 4, 5];
    }
    
    // 場景 9+ 隱藏項目 5
    if ($id > 8) {
        $results['hide_items'] = [5];
    }
    
    return $results;
}

function checkSecondDistance($FireExtin_id, $distance) {
    $pass = false;
    
    switch ($FireExtin_id) {
        case 0:
            $pass = $distance >= 5;
            break;
        case 1:
            $pass = $distance >= 2 && $distance < 4;
            break;
        case 2:
        case 3:
            $pass = $distance >= 3 && $distance < 6;
            break;
    }
    
    return [
        'pass' => $pass,
        'text' => $pass ? '通過' : '不通過',
        'color' => $pass ? 'white' : 'red',
        'value' => number_format($distance, 2)
    ];
}

function checkSecondDistanceNormal($FireExtin_id, $distance) {
    $pass = false;
    
    switch ($FireExtin_id) {
        case 0:
            $pass = $distance >= 5;
            break;
        case 1:
            $pass = $distance >= 2 && $distance < 4;
            break;
        case 2:
        case 3:
            $pass = $distance >= 3 && $distance < 6;
            break;
    }
    
    return [
        'pass' => $pass,
        'text' => $pass ? '通過' : '不通過',
        'color' => $pass ? 'white' : 'red',
        'value' => number_format($distance, 2)
    ];
}
?>
