<?php
// analyze_result.php - 分析遊戲結果並返回評價

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['data'])) {
        sendResponse(false, 'Invalid JSON data or missing data field', null, 400);
    }
    
    // 獲取參數
    $gameData = $input['data'][0]; // 遊戲數據
    $id = $input['id'] ?? null; // 場景 ID
    $FireExtin_id = $input['FireExtin_id'] ?? 0; // 滅火器 ID
    $game_timer = $input['game_timer'] ?? 0; // 遊戲時間
    $TimeLimit = $input['TimeLimit'] ?? 0; // 時間限制
    
    if ($id === null) {
        sendResponse(false, 'Missing required field: id', null, 400);
    }
    
    // 解析 press_data
    $pressData = $gameData['press_data'] ?? [];
    $pressCount = count($pressData);
    $isSuccess = $gameData['is_success'] ?? 0;
    $outfire = $gameData['is_outfire'] ?? 0;
    $sp_wrong = $gameData['is_sp_wrong'] ?? 0;
    
    // 開始評價運算
    $result = analyzeGame($id, $FireExtin_id, $game_timer, $TimeLimit, $pressData, $pressCount, $isSuccess, $outfire, $sp_wrong);
    
    sendResponse(true, 'Analysis completed', $result);
    
} catch(Exception $e) {
    error_log("Analyze Error: " . $e->getMessage());
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}

// 主要評價邏輯函數
function analyzeGame($id, $FireExtin_id, $game_timer, $TimeLimit, $pressData, $pressCount, $isSuccess, $outfire, $sp_wrong) {
    
    if ($id < 5) {
        return analyzeScenes0to4($id, $FireExtin_id, $game_timer, $pressData, $pressCount, $isSuccess, $outfire, $sp_wrong);
    } 
    elseif ($id >= 5 && $id <= 8) {
        return analyzeScenes5to8($id, $FireExtin_id, $game_timer, $pressData, $pressCount, $isSuccess, $sp_wrong);
    } 
    else {
        return analyzeScenes9Plus($id, $FireExtin_id, $game_timer, $TimeLimit, $pressData, $pressCount, $isSuccess, $sp_wrong);
    }
}

// 場景 0-4 的評價邏輯
function analyzeScenes0to4($id, $FireExtin_id, $game_timer, $pressData, $pressCount, $isSuccess, $outfire, $sp_wrong) {
    $count = 0;
    
    if ($pressCount > 1) {
        if ($pressData[0]['Continue_Time'] < 1.5) {
            if ($pressData[0]['Start_Time'] <= 20) {
                $count += 1;
            }
            
            // 根據滅火器類型判斷距離
            switch ($FireExtin_id) {
                case 0:
                    if ($pressData[0]['Distance'] >= 6) $count += 1;
                    if ($pressData[1]['Distance'] >= 5) $count += 1;
                    break;
                case 1:
                    if ($pressData[0]['Distance'] >= 4) $count += 1;
                    if ($pressData[1]['Distance'] >= 2) $count += 1;
                    break;
                case 2:
                case 3:
                    if ($pressData[0]['Distance'] >= 6) $count += 1;
                    if ($pressData[1]['Distance'] >= 3 && $pressData[1]['Distance'] < 6) $count += 1;
                    break;
            }
            
            if (!$outfire) $count += 1;
            if ($pressCount <= 2) $count += 1;
            if ($isSuccess == 1) $count += 1;
        }
    }
    
    // 判斷評級
    if ($count >= 6 && !$sp_wrong) {
        return getPerfectRating($id, $FireExtin_id, $game_timer);
    } else {
        if ($sp_wrong) $count -= 1;
        else $count += 1;
        
        return [
            'rating' => $count >= 5 ? '70分的僥倖成功' : '60分的僥倖成功',
            'medal' => 'none',
            'score' => $count >= 5 ? 70 : 60
        ];
    }
}

// 場景 5-8 的評價邏輯
function analyzeScenes5to8($id, $FireExtin_id, $game_timer, $pressData, $pressCount, $isSuccess, $sp_wrong) {
    $count = 0;
    
    if ($pressCount > 1) {
        if ($pressData[0]['Continue_Time'] < 1.5) {
            if ($pressData[0]['Start_Time'] < 20) $count += 1;
            if ($pressCount == 2) $count += 1;
            if ($isSuccess == 1) $count += 1;
        }
    }
    
    // 判斷評級
    if ($count >= 3 && !$sp_wrong) {
        return getPerfectRatingScenes5to8($id, $FireExtin_id, $game_timer);
    } else {
        if ($sp_wrong) $count -= 1;
        else $count += 1;
        
        return [
            'rating' => $count >= 2 ? '70分的僥倖成功' : '60分的僥倖成功',
            'medal' => 'none',
            'score' => $count >= 2 ? 70 : 60
        ];
    }
}

// 場景 9+ 的評價邏輯
function analyzeScenes9Plus($id, $FireExtin_id, $game_timer, $TimeLimit, $pressData, $pressCount, $isSuccess, $sp_wrong) {
    $count = 0;
    
    if ($pressCount > 1) {
        if ($pressData[0]['Continue_Time'] < 1.5) {
            if ($pressData[0]['Start_Time'] <= 20) $count += 1;
            
            // 根據滅火器類型判斷距離
            switch ($FireExtin_id) {
                case 0:
                    if ($pressData[0]['Distance'] >= 6) $count += 1;
                    if ($pressData[1]['Distance'] >= 5) $count += 1;
                    break;
                case 1:
                    if ($pressData[0]['Distance'] >= 4) $count += 1;
                    if ($pressData[1]['Distance'] >= 2) $count += 1;
                    break;
                case 2:
                case 3:
                    if ($pressData[0]['Distance'] >= 6) $count += 1;
                    if ($pressData[1]['Distance'] >= 3 && $pressData[1]['Distance'] < 6) $count += 1;
                    break;
            }
            
            if ($pressCount <= 2) $count += 1;
            if ($isSuccess == 1) $count += 1;
        }
    }
    
    // 判斷評級
    if ($count >= 5 && !$sp_wrong) {
        return getPerfectRatingScenes9Plus($id, $FireExtin_id, $game_timer);
    } else {
        if ($TimeLimit > 0) $count += 1;
        else $count -= 1;
        
        if ($sp_wrong) $count -= 1;
        else $count += 1;
        
        return [
            'rating' => $count > 5 ? '70分的僥倖成功' : '60分的僥倖成功',
            'medal' => 'none',
            'score' => $count > 5 ? 70 : 60
        ];
    }
}

// 獲取完美評級（場景 0-4）
function getPerfectRating($id, $FireExtin_id, $game_timer) {
    $timeThresholds = [
        0 => [0 => [12, 15], 1 => [13, 15], 2 => [11, 12], 3 => [11, 12]],
        1 => [0 => [16, 18], 1 => [17, 18], 2 => [18, 20], 3 => [18, 20]],
        2 => [0 => [13, 15], 1 => [15, 16], 2 => [12, 14], 3 => [12, 14]],
        3 => [0 => [16, 18], 1 => [17, 18], 2 => [17, 19], 3 => [17, 19]],
        4 => [0 => [12, 13], 1 => [14, 15], 2 => [11, 12], 3 => [11, 12]]
    ];
    
    $thresholds = $timeThresholds[$id][$FireExtin_id] ?? [999, 999];
    
    if ($game_timer < $thresholds[0]) {
        return ['rating' => '金牌高手 成功', 'medal' => 'gold', 'score' => 100];
    } elseif ($game_timer >= $thresholds[0] && $game_timer < $thresholds[1]) {
        return ['rating' => '銀牌高手 成功', 'medal' => 'silver', 'score' => 90];
    } else {
        return ['rating' => '銅牌高手 成功', 'medal' => 'bronze', 'score' => 80];
    }
}

// 獲取完美評級（場景 5-8）
function getPerfectRatingScenes5to8($id, $FireExtin_id, $game_timer) {
    $timeThresholds = [
        5 => [0 => [7, 8], 1 => [7, 8], 2 => [6, 7], 3 => [6, 7]],
        6 => [0 => [8, 9], 1 => [8, 9], 2 => [6, 7], 3 => [6, 7]],
        7 => [0 => [999, 999], 1 => [999, 999], 2 => [999, 999], 3 => [7, 8]],
        8 => [0 => [10, 11], 1 => [10, 11], 2 => [8, 9], 3 => [8, 9]]
    ];
    
    $thresholds = $timeThresholds[$id][$FireExtin_id] ?? [999, 999];
    
    if ($game_timer < $thresholds[0]) {
        return ['rating' => '金牌高手 成功', 'medal' => 'gold', 'score' => 100];
    } elseif ($game_timer >= $thresholds[0] && $game_timer < $thresholds[1]) {
        return ['rating' => '銀牌高手 成功', 'medal' => 'silver', 'score' => 90];
    } else {
        return ['rating' => '銅牌高手 成功', 'medal' => 'bronze', 'score' => 80];
    }
}

// 獲取完美評級（場景 9+）
function getPerfectRatingScenes9Plus($id, $FireExtin_id, $game_timer) {
    $timeThresholds = [
        9 => [0 => [9, 10], 1 => [999, 999], 2 => [9, 10], 3 => [9, 10]],
        10 => [0 => [13, 14], 1 => [13, 14], 2 => [13, 14], 3 => [13, 14]],
        11 => [0 => [14, 15], 1 => [14, 15], 2 => [14, 15], 3 => [14, 15]],
        12 => [0 => [11, 14], 1 => [13, 14], 2 => [13, 14], 3 => [13, 14]],
        13 => [0 => [12, 13], 1 => [13, 14], 2 => [13, 14], 3 => [13, 14]],
        14 => [0 => [14, 15], 1 => [15, 16], 2 => [15, 16], 3 => [15, 16]]
    ];
    
    $thresholds = $timeThresholds[$id][$FireExtin_id] ?? [999, 999];
    
    if ($game_timer < $thresholds[0]) {
        return ['rating' => '金牌高手 成功', 'medal' => 'gold', 'score' => 100];
    } elseif ($game_timer >= $thresholds[0] && $game_timer < $thresholds[1]) {
        return ['rating' => '銀牌高手 成功', 'medal' => 'silver', 'score' => 90];
    } else {
        return ['rating' => '銅牌高手 成功', 'medal' => 'bronze', 'score' => 80];
    }
}
?>
