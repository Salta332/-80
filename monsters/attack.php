<?php
// attack.php
require '../db.php';
require_once 'functions.php';
session_start();

// Включить отладку
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверка аутентификации
if (!isset($_SESSION['username'])) {
    header("Location: ../register.php");
    exit();
}

$username = $_SESSION['username'];
$monster_id = (int)($_POST['monster_id'] ?? 0);

// Начинаем транзакцию для атомарности операций
$conn->begin_transaction();

try {
    // 1. Получаем данные монстра с блокировкой строки
    $stmt = $conn->prepare("SELECT * FROM monsters WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $monster_id);
    $stmt->execute();
    $monster = $stmt->get_result()->fetch_assoc();
    
    if (!$monster) {
        throw new Exception("Монстр не найден!");
    }

    // 2. Получаем данные игрока с блокировкой строки
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? FOR UPDATE");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        throw new Exception("Данные игрока не найдены!");
    }

    // 3. Проверка кулдауна
    $cooldown_end = 0;
    $stmt = $conn->prepare("SELECT cooldown_end FROM player_cooldowns 
                          WHERE username = ? AND monster_id = ?");
    $stmt->bind_param("si", $username, $monster_id);
    $stmt->execute();
    $cooldown = $stmt->get_result()->fetch_assoc();
    
    if ($cooldown && $cooldown['cooldown_end'] > time()) {
        throw new Exception("Монстр еще не восстановился после предыдущей битвы!");
    }

    // 4. Боевая механика
    $player_damage = max(1, $user['strength'] - ($monster['defense'] / 2));
    $monster_damage = max(1, $monster['damage'] - ($user['defense'] / 2));

    // 5. Обновляем HP
    $new_monster_hp = $monster['hp'] - $player_damage;
    $new_user_hp = $user['health'] - $monster_damage;

    // 6. Обработка результатов боя
    $player_died = $new_user_hp <= 0;
    $monster_died = $new_monster_hp <= 0;

    // Если игрок умер - восстанавливаем его HP и HP монстра
    if ($player_died) {
        $new_user_hp = $user['max_health'];
        
        // Восстанавливаем HP монстра
        $stmt = $conn->prepare("UPDATE monsters SET hp = max_hp WHERE id = ?");
        $stmt->bind_param("i", $monster_id);
        $stmt->execute();
        
        // Получаем восстановленный HP монстра из базы
        $stmt = $conn->prepare("SELECT hp FROM monsters WHERE id = ?");
        $stmt->bind_param("i", $monster_id);
        $stmt->execute();
        $new_monster_hp = $stmt->get_result()->fetch_assoc()['hp'];
    }
    
    // Если монстр умер - устанавливаем кулдаун, даем награду и восстанавливаем его HP
    elseif ($monster_died) {
        $duration = getCooldownDuration($monster['rarity']);
        $cooldown_end = time() + $duration;
        
        // Установка кулдауна
        $stmt = $conn->prepare("INSERT INTO player_cooldowns 
                              (username, monster_id, cooldown_end)
                              VALUES (?, ?, ?)
                              ON DUPLICATE KEY UPDATE cooldown_end = ?");
        $stmt->bind_param("siii", $username, $monster_id, $cooldown_end, $cooldown_end);
        $stmt->execute();
        
        // Восстановление HP монстра
        $stmt = $conn->prepare("UPDATE monsters SET hp = ? WHERE id = ?");
        $new_monster_hp = $monster['max_hp'];
        $stmt->bind_param("ii", $new_monster_hp, $monster_id);
        $stmt->execute();
        
        // Награда
        updateUserRewards($username, $monster);
    }

    // 7. Сохраняем HP игрока
    $stmt = $conn->prepare("UPDATE users SET health = ? WHERE username = ?");
    $stmt->bind_param("is", $new_user_hp, $username);
    $stmt->execute();

    // Фиксируем транзакцию
    $conn->commit();

    // Возвращаем результат
    echo json_encode([
        'success' => true,
        'monster_hp' => max(0, $new_monster_hp),
        'player_hp' => $new_user_hp,
        'player_died' => $player_died,
        'monster_died' => $monster_died,
        'player_damage' => $player_damage,
        'monster_damage' => $monster_damage
    ]);

} catch (Exception $e) {
    // Откатываем транзакцию при ошибке
    $conn->rollback();
    
    error_log("Attack error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
}

function getCooldownDuration($rarity) {
    return match(strtolower($rarity)) {
        'common' => 4 * 3600,  // 4 часа
        'rare'   => 8 * 3600,   // 8 часов
        'epic'   => 12 * 3600, // 12 часов
        default  => 4 * 3600    // по умолчанию 4 часа
    };
}