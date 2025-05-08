<?php
// functions.php
require '../db.php';

// Таблица наград для монстров по ID
$monster_rewards = [
    // ID монстра => [монеты, опыт, золото, кристаллы]
    // Обычный монстр 
    1 => [25, 10, 0, 0],    
    2 => [30, 17, 0, 0],    
    3 => [35, 20, 0, 0],   
    4 => [45, 22, 0, 0],  
    5 => [50, 25, 5, 0],
    // Редкий монстр
    6 => [50, 50, 5, 0],
    7 => [75, 75, 7, 2],
    8 => [100, 100, 10, 1],
    9 => [125, 125, 15, 1],
    10 => [150, 150, 20, 2],  
    // Эпический монстр
    11 => [200, 300, 30, 3],
    12 => [300, 400, 50, 4],
    13 => [400, 500, 80, 5],
    14 => [500, 600, 110, 7],
    15 => [750, 750, 150, 10],
];

// Функция получения данных пользователя
function getUserData($username) {
    global $conn;
    $query = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $query->bind_param("s", $username);
    $query->execute();
    return $query->get_result()->fetch_assoc();
}

function restoreHealthOverTime($conn, $username) {
    $stmt = $conn->prepare("UPDATE users 
                          SET health = max_health
                          WHERE username = ? AND health < max_health");
    $stmt->bind_param("s", $username);
    $stmt->execute();
}

// Функция получения данных о монстре
function getMonsterData($monster_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM monsters WHERE id = ?");
    
    if (!$stmt) {
        error_log("Ошибка подготовки запроса: " . $conn->error);
        return false;
    }

    $stmt->bind_param("i", $monster_id);
    if (!$stmt->execute()) {
        error_log("Ошибка выполнения запроса: " . $stmt->error);
        return false;
    }

    $result = $stmt->get_result();
    if (!$result) {
        error_log("Ошибка получения результата: " . $stmt->error);
        return false;
    }

    $monster = $result->fetch_assoc();
    
    error_log("Результат getMonsterData: " . json_encode($monster));

    return $monster; // false, если монстра нет
}


function getRemainingCooldown($cooldown_end) {
    return max(0, $cooldown_end - time());
}

// Функция проверки, может ли игрок атаковать
function canAttack($conn, $username, $monster_id, $rarity) {
    $last_attack = getLastAttackTime($conn, $username, $monster_id);
    $cooldown = getCooldown($rarity);
    return (time() - $last_attack) >= $cooldown;
}

// Функция получения времени последней атаки
function getLastAttackTime($conn, $username, $monster_id) {
    $query = $conn->prepare("SELECT last_attack FROM attack_limits WHERE username = ? AND monster_id = ?");
    $query->bind_param("si", $username, $monster_id);
    $query->execute();
    $result = $query->get_result();
    return $result->num_rows > 0 ? strtotime($result->fetch_assoc()['last_attack']) : 0;
}

// Функция обновления времени последней атаки
function updateAttackLimit($conn, $username, $monster_id) {
    $query = $conn->prepare("
        INSERT INTO attack_limits (username, monster_id, last_attack)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_attack = NOW()
    ");
    $query->bind_param("si", $username, $monster_id);
    $query->execute();
}

// Функция расчета времени кулдауна атаки в зависимости от редкости монстра
function getCooldown($rarity) {
    switch($rarity) {
        case 'common': return 4 * 60 * 60;   // 4 часа
        case 'rare':   return 6 * 60 * 60;   // 6 часов
        case 'epic':   return 10 * 60 * 60;  // 10 часов
        default:       return 4 * 60 * 60;   // По умолчанию 4 часа
    }
}

// Функция нанесения урона
function calculateDamage($attacker_damage, $defender_defense) {
    return max(1, $attacker_damage - $defender_defense);
}


?>


