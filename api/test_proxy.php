<?php
header('Content-Type: application/json');

$proxy_env = getenv("PROXY_URL");

if (!$proxy_env) {
    echo json_encode([
        "status" => "error",
        "message" => "PROXY_URL 環境變數未設定"
    ]);
    exit;
}

$proxy    = parse_url($proxy_env);
$proxyUrl = $proxy['host'] . ":" . $proxy['port'];
$proxyAuth = $proxy['user'] . ":" . $proxy['pass'];

// 測試1：不走 Proxy 的真實 IP
$ch1 = curl_init();
curl_setopt($ch1, CURLOPT_URL, "https://api.ipify.org?format=json");
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
$direct_ip = curl_exec($ch1);
curl_close($ch1);

// 測試2：走 Proxy 的出口 IP
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, "https://api.ipify.org?format=json");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch2, CURLOPT_PROXY,        $proxyUrl);
curl_setopt($ch2, CURLOPT_PROXYAUTH,    CURLAUTH_BASIC);
curl_setopt($ch2, CURLOPT_PROXYUSERPWD, $proxyAuth);
$proxy_ip = curl_exec($ch2);
$proxy_err = curl_error($ch2);
curl_close($ch2);

echo json_encode([
    "render_direct_ip" => json_decode($direct_ip)->ip ?? "取得失敗",
    "proxy_ip"         => $proxy_err ? "錯誤：$proxy_err" : (json_decode($proxy_ip)->ip ?? "取得失敗"),
    "proxy_url_set"    => $proxyUrl,
    "proxy_match_vps"  => !$proxy_err && json_decode($proxy_ip) ? "請對照 VPS Public IP 確認" : "連線失敗"
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
