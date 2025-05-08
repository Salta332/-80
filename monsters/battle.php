<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', 'error.log');

session_start();
header('Content-Type: application/json');

try {
    require '../db.php';
    // require_once 'game_function.php';

    if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        throw new Exception("Вы не авторизованы!");
    }

    if (!isset($_POST['monster_id']) || !is_numeric($_POST['monster_id'])) {
        throw new Exception("Неверный ID монстра");
    }

    $username = $_SESSION['username'];
    $monster_id = intval($_POST['monster_id']);

    // Получаем данные игрока
    $query = $conn->prepare("SELECT lvl, strength, health, defense, exp, coin, gold, crystals FROM users WHERE username = ?");
    $query->bind_param("s", $username);
    $query->execute();
    $user = $query->get_result()->fetch_assoc();
    if (!$user) {
        throw new Exception("Игрок не найден");
    }

    // Получаем данные монстра
    $query = $conn->prepare("SELECT id, level, hp, damage, defense, rarity FROM monsters WHERE id = ?");
    $query->bind_param("i", $monster_id);
    $query->execute();
    $monster = $query->get_result()->fetch_assoc();
    if (!$monster) {
        throw new Exception("Монстр не найден");
    }

    // Проверяем лимит атаки для конкретного монстра
    if (!canAttack($conn, $username, $monster_id, $monster['rarity'])) {
        echo json_encode(["status" => "error", "message" => "Вы не можете атаковать этого монстра сейчас!"]);
        exit();
    }

    // Симуляция боя
    $player_hp = $user['health'];
    $monster_hp = $monster['hp'];
    $battle_log = [];

    while ($player_hp > 0 && $monster_hp > 0) {
        $critical_hit = rand(1, 100) <= 10 ? 1.5 : 1;
        $user_damage = max(1, round(($user['strength'] * 1.1 - $monster['defense'] * 0.8) * $critical_hit));
        $monster_damage = max(1, round($monster['damage'] * (rand(90, 110) / 100) - $user['defense'] * 0.5));

        $monster_hp -= $user_damage;
        $battle_log[] = "Вы нанесли $user_damage урона. Здоровье монстра: $monster_hp";

        if ($monster_hp <= 0) {
            break;
        }

        $player_hp -= $monster_damage;
        $battle_log[] = "Монстр нанес вам $monster_damage урона. Ваше здоровье: $player_hp";
    }

    if ($player_hp > 0) {
        $rarity_bonus = match ($monster['rarity']) {
            'common' => 1,
            'rare'   => 1.5,
            'epic'   => 2,
            default  => 1,
        };

        $reward_exp   = round(($monster['level'] * 10 + $monster['hp'] / 50) * $rarity_bonus);
        $reward_coin  = round(($monster['level'] * 5 + $monster['damage'] / 25) * $rarity_bonus);
        $reward_gold  = in_array($monster['rarity'], ['rare','epic']) ? round($monster['level'] * 2) : 0;
        $reward_crystal = $monster['rarity'] === 'epic' ? round($monster['level'] * 1.5) : 0;

        $new_exp   = $user['exp'] + $reward_exp;
        $new_coin  = $user['coin'] + $reward_coin;
        $new_gold  = $user['gold'] + $reward_gold;
        $new_crystal = $user['crystals'] + $reward_crystal;

        $update = $conn->prepare("UPDATE users SET exp = ?, coin = ?, gold = ?, crystals = ? WHERE username = ?");
        $update->bind_param("iiiis", $new_exp, $new_coin, $new_gold, $new_crystal, $username);
        $update->execute();

        // Обновляем время последней атаки в таблице attack_limits
        updateAttackLimit($conn, $username, $monster_id);

        $response = [
            "status"     => "win",
            "exp"        => $reward_exp,
            "coin"       => $reward_coin,
            "gold"       => $reward_gold,
            "crystals"   => $reward_crystal,
            "battle_log" => $battle_log
        ];
        echo json_encode($response);
    } else {
        $response = [
            "status"     => "lose",
            "battle_log" => $battle_log
        ];
        echo json_encode($response);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}

ob_end_flush();

