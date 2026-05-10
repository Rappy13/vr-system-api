<?php
// analyze_details.php - 分析游戏详细结果（通过/不通过判断）

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
        'check_start_time' => ['pass' => false, 'text' => '不通过', 'color' => 'red', 'value' => ''],
        'check_first_distance' => ['pass' => false, 'text' => '不通过', 'color' => 'red', 'value' => '0'],
        'check_second_distance' => ['pass' => false, 'text' => '不通过', 'color' => 'red', 'value' => '0'],
        'check_no_outfire' => ['pass' => false, 'text' => '不通过', 'color' => 'red'],
        'check_press_count' => ['pass' => false, 'text' => '不通过', 'color' => 'red'],
        'check_success' => ['pass' => false, 'text' => '不通过', 'color' => 'red'],
        'final_result' => ['pass' => false, 'text' => '由教官说明', 'color' => 'red'],
        'kill_time' => number_format($kill_fire_time, 2) . '秒',
        'special_error' => ['text' => '无', 'color' => 'white'],
        'is_perfect' => 0
    ];
    
    $count = 0;
    
    if ($pressCount > 1) {
        if ($pressData[0]['Continue_Time'] > 1.5) {
            // 第一次按压时间过长
            $results['check_first_distance']['value'] = number_format($pressData[0]['Distance'], 2);
            $results['check_second_distance'] = checkSecondDistance($FireExtin_id, $pressData[0]['Distance']);
        } else {
            // 正常流程
            // 检查开始时间
            if ($pressData[0]['Start_Time'] <= 20) {
                $results['check_start_time'] = ['pass' => true, 'text' => '通过', 'color' => 'white'];
                $count++;
            }
            
            // 检查第一次距离
            if ($pressData[0]['Distance'] > 6) {
                $results['check_first_distance'] = ['pass' => true, 'text' => '通过', 'color' => 'white', 'value' => number_format($pressData[0]['Distance'], 2)];
                $count++;
            } else {
                $results['check_first_distance']['value'] = number_format($pressData[0]['Distance'], 2);
            }
            
            // 检查第二次距离
            $secondCheck = checkSecondDistanceNormal($FireExtin_id, $pressData[1]['Distance']);
            $results['check_second_distance'] = $secondCheck;
            if ($secondCheck['pass']) $count++;
        }
        
        // 检查是否灭火
        if (!$outfire) {
            $results['check_no_outfire'] = ['pass' => true, 'text' => '通过', 'color' => 'white'];
            $count++;
        }
        
        // 检查按压次数
        if ($pressCount <= 2) {
            $results['check_press_count'] = ['pass' => true, 'text' => '通过', 'color' => 'white'];
            $count++;
        }
        
    } elseif ($pressCount == 1) {
        $results['check_second_distance'] = checkSecondDistance($FireExtin_id, $pressData[0]['Distance']);
        $results['check_second_distance']['value'] = number_format($pressData[0]['Distance'], 2);
        
        if (!$outfire) {
            $results['check_no_outfire'] = ['pass' => true, 'text' => '通过', 'color' => 'white'];
        }
        
        $results['check_press_count'] = ['pass' => true, 'text' => '通过', 'color' => 'white'];
    }
    // pressCount == 0 时所有项目保持初始值（不通过）
    
    // 检查成功
    if ($isSuccess == 1) {
        $results['check_success'] = ['pass' => true, 'text' => '通过', 'color' => 'white'];
        $count++;
    }
    
    // 判断最终结果
    $requiredCount = 6;
    if ($id >= 5 && $id <= 8) {
        $requiredCount = 3;
    } elseif ($id > 8) {
        $requiredCount = 5;
    }
    
    if ($count >= $requiredCount && $TimeLimit > 0 && !$sp_wrong) {
        $results['final_result'] = ['pass' => true, 'text' => '通过', 'color' => 'white'];
        $results['is_perfect'] = 1;
    }
    
    // 特殊错误判断
    if ($sp_wrong) {
        $errorMessages = [
            1 => '先打上方油盘',
            4 => '发生回火',
            8 => '问答题选错',
            10 => '先打错误火源',
            13 => '先打错误火源',
            14 => '发生回火'
        ];
        
        $results['special_error'] = [
            'text' => $errorMessages[$id] ?? '特殊错误',
            'color' => 'red'
        ];
    }
    
    // 特殊场景提示
    if ($id == 14 && $FireExtin_id == 0) {
        $results['final_result']['text'] = '干粉容易出现爆炸&火花';
        $results['final_result']['color'] = 'red';
    }
    
    // 场景 5-8 隐藏部分项目
    if ($id >= 5 && $id <= 8) {
        $results['hide_items'] = [1, 2, 3, 4, 5];
    }
    
    // 场景 9+ 隐藏项目 5
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
        'text' => $pass ? '通过' : '不通过',
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
        'text' => $pass ? '通过' : '不通过',
        'color' => $pass ? 'white' : 'red',
        'value' => number_format($distance, 2)
    ];
}
?>
