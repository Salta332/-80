<?php
function getUserData($conn, $username) {
    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function generateOpponent($player_level, $difficulty = 'medium') {
    $difficulty_multipliers = [
        'easy' => 0.7,
        'medium' => 1.0,
        'hard' => 1.5
    ];
    
    $multiplier = $difficulty_multipliers[$difficulty] ?? 1.0;
    $level = max(1, $player_level + rand(-2, 2));
    
    $opponents = [
        ['name' => 'Гоблин-разбойник', 'image' => 'goblin'],
        ['name' => 'Орк-воин', 'image' => 'orc'],
        ['name' => 'Темный маг', 'image' => 'mage'],
        ['name' => 'Скелет-воин', 'image' => 'skeleton'],
        ['name' => 'Лесной тролль', 'image' => 'troll']
    ];
    
    $opponent = $opponents[array_rand($opponents)];
    
    return [
        'name' => $opponent['name'],
        'image' => $opponent['image'],
        'lvl' => $level,
        'health' => 50 + ($level * 10 * $multiplier),
        'strength' => 5 + ($level * 2 * $multiplier),
        'defense' => 3 + ($level * 1.5 * $multiplier)
    ];
}

function findPvPOpponent($conn, $player_level) {
    $min_level = max(1, $player_level - 3);
    $max_level = $player_level + 3;
    
    $query = "SELECT username, lvl, strength, health, defense, avatar_path 
              FROM users 
              WHERE username != ? AND lvl BETWEEN ? AND ? AND health >= 10
              ORDER BY RAND() LIMIT 1";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $_SESSION['username'], $min_level, $max_level);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function simulateBattle($player, $opponent, $is_pvp = false) {
    $log = [];
    $player_health = $player['health'];
    $opponent_health = $opponent['health'];
    
    $log[] = [
        'type' => 'info',
        'text' => "Бой начался! ".$player['username']." vs ".($is_pvp ? $opponent['username'] : $opponent['name']).""
    ];
    
    $max_rounds = 10;
    $round = 1;
    
    while ($player_health > 0 && $opponent_health > 0 && $round <= $max_rounds) {
        // Игрок атакует
        $player_damage = max(1, $player['strength'] * (0.8 + mt_rand(0, 40) / 100) - $opponent['defense'] * 0.2);
        $opponent_health -= $player_damage;
        $log[] = [
            'type' => 'player',
            'text' => "Раунд $round: Вы нанесли ".round($player_damage, 1)." урона"
        ];
        
        if ($opponent_health <= 0) break;
        
        // Противник атакует
        $opponent_damage = max(1, $opponent['strength'] * (0.8 + mt_rand(0, 40) / 100) - $player['defense'] * 0.2);
        $player_health -= $opponent_damage;
        $log[] = [
            'type' => 'enemy',
            'text' => "Раунд $round: Противник нанес вам ".round($opponent_damage, 1)." урона"
        ];
        
        $round++;
    }
    
    $won = $opponent_health <= 0;
    $health_lost = $won ? 0 : $player['health'] - $player_health;
    $reward = calculateReward($player, $opponent, $won, $is_pvp);
    
    return [
        'won' => $won,
        'health_lost' => $health_lost,
        'reward' => $reward,
        'log' => $log,
        'opponent' => $opponent
    ];
}

function calculateReward($player, $opponent, $won, $is_pvp) {
    if (!$won) return ['exp' => 0, 'gold' => 0, 'crystals' => 0];
    
    $base_exp = $opponent['lvl'] * 5;
    $base_gold = $opponent['lvl'] * 3;
    
    if ($is_pvp) {
        $base_exp *= 1.5;
        $base_gold *= 1.5;
        $crystals = max(1, floor($opponent['lvl'] / 2));
    } else {
        $crystals = 0;
    }
    
    return [
        'exp' => round($base_exp),
        'gold' => round($base_gold),
        'crystals' => $crystals
    ];
}

function processBattleResult($conn, $username, $battle_result, $is_pvp) {
    $conn->begin_transaction();
    
    try {
        if ($battle_result['health_lost'] > 0) {
            $stmt = $conn->prepare("UPDATE users SET health = GREATEST(0, health - ?) WHERE username = ?");
            $stmt->bind_param("is", $battle_result['health_lost'], $username);
            $stmt->execute();
        }
        
        if ($battle_result['won']) {
            $reward = $battle_result['reward'];
            
            $stmt = $conn->prepare("UPDATE users SET exp = exp + ?, gold = gold + ? WHERE username = ?");
            $stmt->bind_param("iis", $reward['exp'], $reward['gold'], $username);
            $stmt->execute();
            
            if ($is_pvp) {
                $stmt = $conn->prepare("UPDATE users SET pvp_wins = pvp_wins + 1, arena_rating = arena_rating + 10 WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
            }
            
            if ($reward['crystals'] > 0) {
                $stmt = $conn->prepare("UPDATE users SET crystals = crystals + ? WHERE username = ?");
                $stmt->bind_param("is", $reward['crystals'], $username);
                $stmt->execute();
            }
        }
        
        // Запись в историю боев
        $opponent_name = $is_pvp ? $battle_result['opponent']['username'] : $battle_result['opponent']['name'];
        $stmt = $conn->prepare("INSERT INTO battle_history 
                               (username, is_pvp, opponent_name, won, exp_gained, gold_gained, crystals_gained, health_lost) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisiiiii", 
            $username,
            $is_pvp,
            $opponent_name,
            $battle_result['won'],
            $battle_result['reward']['exp'],
            $battle_result['reward']['gold'],
            $battle_result['reward']['crystals'],
            $battle_result['health_lost']
        );
        $stmt->execute();
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function getArenaTopPlayers($conn) {
    $query = "SELECT username, lvl, pvp_wins, arena_rating, avatar_path 
              FROM users 
              ORDER BY arena_rating DESC, pvp_wins DESC 
              LIMIT 10";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getBattleHistory($conn, $username, $limit = 10) {
    $query = "SELECT * FROM battle_history 
              WHERE username = ? 
              ORDER BY battle_date DESC 
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $username, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>