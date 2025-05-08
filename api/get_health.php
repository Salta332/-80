<?php
require '../db.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT health, max_health FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

header('Content-Type: application/json');
echo json_encode([
    'health' => $result['health'],
    'max_health' => $result['max_health']
]);
?>