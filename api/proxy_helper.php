<?php
function proxied_curl($url, $post_data = null, $headers = []) {
    $proxy_env = getenv("PROXY_URL");
    $proxy     = parse_url($proxy_env);
    $proxyUrl  = $proxy['host'] . ":" . $proxy['port'];
    $proxyAuth = $proxy['user'] . ":" . $proxy['pass'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_PROXY,          $proxyUrl);
    curl_setopt($ch, CURLOPT_PROXYAUTH,      CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_PROXYUSERPWD,   $proxyAuth);

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($post_data !== null) {
        curl_setopt($ch, CURLOPT_POST,       1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    }

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ["error" => $err];
    return $response;
}
