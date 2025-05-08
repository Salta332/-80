<?php
// level_system.php - система опыта и уровней

session_start(); // Начало сессии

require_once 'db.php';
// Подготовка переменной и запрос данных пользователя
$username = $_SESSION['username'];
$user_query = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($user_query);
if (!$stmt) {
    die("Ошибка в подготовке запроса: " . $conn->error);    
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc(); // Извлечение данных

// Таблица опыта для уровней
$level_experience = [
    1 => 26, 2 => 67, 3 => 176, 4 => 328, 5 => 610, 6 => 989, 7 => 1222, 8 => 1620, 9 => 2091, 10 => 2455,
    11 => 3095, 12 => 4043, 13 => 4623, 14 => 5234, 15 => 5925, 16 => 6799, 17 => 7834, 18 => 8973, 19 => 10001, 20 => 13002
];

// Таблица наград за уровни
$level_rewards = [
    1 => ['health' => 10, 'strength' => 5, 'defense' => 5, 'gold' => 5],
    2 => ['health' => 20, 'strength' => 10, 'defense' => 10, 'gold' => 10],
    3 => ['health' => 30, 'strength' => 15, 'defense' => 15, 'gold' => 15],
    4 => ['health' => 40, 'strength' => 20, 'defense' => 20, 'gold' => 20],
    5 => ['health' => 50, 'strength' => 25, 'defense' => 25, 'gold' => 25],
    6 => ['health' => 60, 'strength' => 30, 'defense' => 30, 'gold' => 30],
    7 => ['health' => 70, 'strength' => 35, 'defense' => 35, 'gold' => 35],
    8 => ['health' => 80, 'strength' => 40, 'defense' => 40, 'gold' => 40],
    9 => ['health' => 90, 'strength' => 45, 'defense' => 45, 'gold' => 45],
    10 => ['health' => 100, 'strength' => 50, 'defense' => 50, 'gold' => 50],
    // Продолжаем по аналогии до 20 уровня
    11 => ['health' => 110, 'strength' => 55, 'defense' => 55, 'gold' => 55],
    12 => ['health' => 120, 'strength' => 60, 'defense' => 60, 'gold' => 60],
    13 => ['health' => 130, 'strength' => 65, 'defense' => 65, 'gold' => 65],
    14 => ['health' => 140, 'strength' => 70, 'defense' => 70, 'gold' => 70],
    15 => ['health' => 150, 'strength' => 75, 'defense' => 75, 'gold' => 75],
    16 => ['health' => 160, 'strength' => 80, 'defense' => 80, 'gold' => 80],
    17 => ['health' => 170, 'strength' => 85, 'defense' => 85, 'gold' => 85],
    18 => ['health' => 180, 'strength' => 90, 'defense' => 90, 'gold' => 90],
    19 => ['health' => 190, 'strength' => 95, 'defense' => 95, 'gold' => 95],
    20 => ['health' => 200, 'strength' => 100, 'defense' => 100, 'gold' => 100]
];

function processHealthRegeneration($user_data, $conn) {
    $current_time = time();
    $time_since_last_regen = $current_time - ($user_data['last_regen_time'] ?? 0);
    
    // Если прошло больше 1 секунды и здоровье не полное
    if ($time_since_last_regen > 0 && $user_data['health'] < $user_data['max_health']) {
        // Рассчитываем количество восстановленного здоровья
        $regen_amount = min(
            ($user_data['health_regen_rate'] ?? 5) * $time_since_last_regen,
            $user_data['max_health'] - $user_data['health']
        );
        
        if ($regen_amount > 0) {
            $new_health = $user_data['health'] + $regen_amount;
            $stmt = $conn->prepare("UPDATE users SET health = ?, last_regen_time = ? WHERE username = ?");
            $stmt->bind_param("iis", $new_health, $current_time, $user_data['username']);
            $stmt->execute();
            $user_data['health'] = $new_health;
        }
    }
    
    return $user_data;
}

function checkLevelUp($user_data, $conn) {
    global $level_experience, $level_rewards;

    if (!is_array($user_data) || !isset($user_data['lvl']) || !isset($user_data['exp'])) {
        return ['lvl' => 1, 'exp' => 0];
    }
    
    $lvl = $user_data['lvl'];
    $exp = $user_data['exp'];
    $initial_lvl = $lvl;
    $levels_gained = 0;

    // Проверяем повышение уровней
    while (isset($level_experience[$lvl]) && $exp >= $level_experience[$lvl]) {
        $exp -= $level_experience[$lvl];
        $lvl++;
        $levels_gained++;
    }

    if ($levels_gained > 0) {
        // Рассчитываем суммарные награды за все полученные уровни
        $total_rewards = [
            'health' => 0,
            'strength' => 0,
            'defense' => 0,
            'gold' => 0,
            'regen_rate' => 0
        ];
        
        for ($i = $initial_lvl; $i < $initial_lvl + $levels_gained; $i++) {
            if (isset($level_rewards[$i])) {
                $total_rewards['health'] += $level_rewards[$i]['health'];
                $total_rewards['strength'] += $level_rewards[$i]['strength'];
                $total_rewards['defense'] += $level_rewards[$i]['defense'];
                $total_rewards['gold'] += $level_rewards[$i]['gold'];
                $total_rewards['regen_rate'] += 1; // +1 к регенерации за каждый уровень
            }
        }
        
        // Применяем награды
        $new_regen_rate = ($user_data['health_regen_rate'] ?? 5) + $total_rewards['regen_rate'];
        
        $stmt = $conn->prepare("UPDATE users SET 
            lvl = ?, 
            exp = ?,
            max_health = max_health + ?,
            strength = strength + ?,
            defense = defense + ?,
            gold = gold + ?,
            health_regen_rate = ?,
            health = LEAST(max_health + ?, health + ?) -- Восстанавливаем часть здоровья
            WHERE id = ?");
        
        $stmt->bind_param(
            "iiiiiiiiii", 
            $lvl, 
            $exp,
            $total_rewards['health'],
            $total_rewards['strength'],
            $total_rewards['defense'],
            $total_rewards['gold'],
            $new_regen_rate,
            $total_rewards['health'],
            $total_rewards['health'], // Восстанавливаем здоровье равное добавленному максимуму
            $user_data['id']
        );
        
        $stmt->execute();
        
        // Обновляем данные пользователя
        $user_data['lvl'] = $lvl;
        $user_data['exp'] = $exp;
        $user_data['max_health'] += $total_rewards['health'];
        $user_data['strength'] += $total_rewards['strength'];
        $user_data['defense'] += $total_rewards['defense'];
        $user_data['gold'] += $total_rewards['gold'];
        $user_data['health_regen_rate'] = $new_regen_rate;
        $user_data['health'] = min($user_data['health'] + $total_rewards['health'], $user_data['max_health']);
    }

    return $user_data;
}

function getExpProgress($lvl, $exp) {
    global $level_experience;
    
    $needed_exp = $level_experience[$lvl] ?? 1; // Чтобы избежать деления на 0
    return min(100, ($exp / $needed_exp) * 100); // Процент заполнения шкалы
}



// Преобразование чисел
function formatNumber($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'm'; // Миллионы
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'k'; // Тысячи
    } else {
        return $number; // Меньше 1000
    }
}


$strength = formatNumber($user_data['strength']);
$health = formatNumber($user_data['health']);
$defense = formatNumber($user_data['defense']);
$exp = formatNumber($user_data['exp']);
$coin = formatNumber($user_data['coin']);
$gold = formatNumber($user_data['gold']);
$crystals = formatNumber($user_data['crystals']);

?>