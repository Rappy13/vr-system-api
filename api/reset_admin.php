<?php
require_once __DIR__ . '/config.php';
$pdo = getDBConnection();

$newHash = password_hash('admin1234', PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE `User` SET password = ? WHERE username = 'admin'");
$stmt->execute([$newHash]);

echo json_encode(['success' => true, 'hash' => $newHash]);
