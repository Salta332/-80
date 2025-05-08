<?php
require '../db.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

$monster_id = $_GET['monster_id'] ?? 0;
$username = $_SESSION['username'];

if ($monster_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid monster ID']);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM player_cooldowns WHERE username = ? AND monster_id = ?");
    $stmt->bind_param("si", $username, $monster_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}