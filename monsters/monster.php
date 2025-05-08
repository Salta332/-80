<?php
ob_start();
// monster.php
require '../db.php';
require_once '../level_system.php';
require_once 'functions.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../register.php");
    exit();
}

$username = $_SESSION['username'];
$monster_id = $_GET['id'] ?? 0;


if ($monster_id <= 0) {
    header("Location: monsters.php");
    exit();
}
// Инициализация переменных по умолчанию
$user_data = [];
$monster = [];
$exp_progress = 0;
$strength = $health = $defense = $exp = $coin = $gold = $crystals = 0;

// Получение данных пользователя
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();

if (!$user_data) {
    die("Пользователь не найден");
}

$avatar = $user_data['avatar_path'] ?? 0;
$donut = $user_data['donut'] ?? 0;
$exp = formatNumber($user_data['exp'] ?? 0);
$strength = formatNumber($user_data['strength'] ?? 0);
$health = formatNumber($user_data['health'] ?? 0);
$defense = formatNumber($user_data['defense'] ?? 0);
$coin = formatNumber($user_data['coin'] ?? 0);
$gold = formatNumber($user_data['gold'] ?? 0);
$crystals = formatNumber($user_data['crystals'] ?? 0);

$user_data = processHealthRegeneration($user_data, $conn);


if ($user_result->num_rows > 0) {


    // Расчет прогресса опыта
    $exp_progress = getExpProgress($user_data['lvl'] ?? 1, $exp);
}

if (($user_data['health'] ?? 0) <= 0) {
    // Восстанавливаем здоровье после смерти
    $stmt = $conn->prepare("UPDATE users SET health = max_health WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    header("Location: death.php"); // Перенаправляем на страницу смерти
    exit();
}

// Получаем данные монстра
$monster_data = [];
if ($monster_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM monsters WHERE id = ?");
    $stmt->bind_param("i", $monster_id);
    $stmt->execute();
    $monster_result = $stmt->get_result();
    
    if ($monster_result->num_rows > 0) {
        $monster = $monster_result->fetch_assoc();
    }
}
function getCooldownDuration($rarity) {
    return match(strtolower($rarity)) {
        'common' => 4 * 3600,
        'rare'   => 8 * 3600,
        'epic'   => 12 * 3600,
        default  => 4 * 3600
    };
}

function updateUserRewards($username, $monster) {
    global $conn, $monster_rewards;
    
    // Получаем награды из таблицы по ID монстра
    $rewards = $monster_rewards[$monster['id']] ?? [25, 10, 0, 0]; // Значения по умолчанию
    
    $reward = [
        'coin' => $rewards[0],
        'exp' => $rewards[1],
        'gold' => $rewards[2],
        'crystals' => $rewards[3]
    ];
    
    try {
        $stmt = $conn->prepare("UPDATE users 
            SET coin = coin + ?,
                exp = exp + ?,
                gold = gold + ?,
                crystals = crystals + ?,
                health = LEAST(max_health, health + 20)
            WHERE username = ?");
        $stmt->bind_param("iiiis", 
            $reward['coin'], 
            $reward['exp'],
            $reward['gold'],
            $reward['crystals'],
            $username);
        $stmt->execute();
        
        return $reward;
    } catch (Exception $e) {
        error_log("Reward error: " . $e->getMessage());
        return false;
    }
}

// Обработка атаки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($monster)) {
    // Рассчитываем урон
    $player_damage = max(1, ($user_data['strength'] ?? 0) - ($monster['defense'] ?? 0)/10);
    $monster_damage = max(1, ($monster['damage'] ?? 0) - ($user_data['defense'] ?? 0)/10);

    // Обновляем HP
    $monster['hp'] = ($monster['hp'] ?? 0) - $player_damage;
    $user_data['health'] = ($user_data['health'] ?? 0) - $monster_damage;

    // Проверяем, умер ли монстр
    if ($monster['hp'] <= 0) {
        // Проверяем, не истек ли уже кулдаун
        $current_time = time();
        $stmt = $conn->prepare("SELECT cooldown_end FROM player_cooldowns WHERE username = ? AND monster_id = ?");
        $stmt->bind_param("si", $username, $monster_id);
        $stmt->execute();
        $cooldown_result = $stmt->get_result();
        
        // Устанавливаем кулдаун только если его нет или он истек
        if ($cooldown_result->num_rows == 0 || ($cooldown_row = $cooldown_result->fetch_assoc()) && $cooldown_row['cooldown_end'] < $current_time) {
            $duration = getCooldownDuration($monster['rarity']);
            $cooldown_end = $current_time + $duration;
            
            $stmt = $conn->prepare("INSERT INTO player_cooldowns 
                (username, monster_id, cooldown_end)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE cooldown_end = ?");
            $stmt->bind_param("siii", $username, $monster_id, $cooldown_end, $cooldown_end);
            $stmt->execute();
        }
        
        // Восстанавливаем HP монстра
        $stmt = $conn->prepare("UPDATE monsters SET hp = max_hp WHERE id = ?");
        $stmt->bind_param("i", $monster_id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE users SET health = health WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        
        // Получаем и выдаем награду
        $reward = updateUserRewards($username, $monster);
    
        $reward_message = "Победа! Получено: ";
        $parts = [];
        if ($reward['coin'] > 0) $parts[] = $reward['coin']." монет";
        if ($reward['exp'] > 0) $parts[] = $reward['exp']." опыта";
        if ($reward['gold'] > 0) $parts[] = $reward['gold']." золота";
        if ($reward['crystals'] > 0) $parts[] = $reward['crystals']." кристаллов";
        
        header("Location: monsters.php?victory=1&reward=".urlencode($reward_message.implode(", ", $parts)));
        exit();
        
    }
    
    // Проверяем, умер ли игрок
    if (($user_data['health'] ?? 0) <= 0) {
        // Вместо полного восстановления устанавливаем здоровье на 1 и время восстановления
        $current_time = time();
        $stmt = $conn->prepare("UPDATE users 
            SET health = 1, 
                last_regen_time = ?
            WHERE username = ?");
        $stmt->bind_param("is", $current_time, $username);
        $stmt->execute();
        
        // Восстанавливаем здоровье монстра
        $stmt = $conn->prepare("UPDATE monsters SET hp = max_hp WHERE id = ?");
        $stmt->bind_param("i", $monster_id);
        $stmt->execute();
        
        // Сообщение о поражении
        $defeat_message = "Поражение! Приходи когда станешь сильнее.";
        header("Location: monsters.php?id=$monster_id&defeat=1&message=".urlencode($defeat_message));
        exit();
    }

    // Сохраняем текущее состояние боя
    $stmt = $conn->prepare("UPDATE monsters SET hp = ? WHERE id = ?");
    $stmt->bind_param("ii", $monster['hp'], $monster_id);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE users SET health = ? WHERE username = ?");
    $stmt->bind_param("is", $user_data['health'], $username);
    $stmt->execute();

    header("Location: monster.php?id=$monster_id");
    exit();

}
ob_end_flush();
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Монстры</title>
    <link rel="stylesheet" href="../css/all.css">
    <script src="../js/time.js"></script>
    <script src="../js/regeneration.js"></script>
    <script src="attack.js"></script>
<style>
    .monster-image, .player {
        width: 100px; /* Размер аватарок */
        height: 100px;
        border-radius: 10px;
    }
</style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="onexp">
                <span>
                    <img class="icons" src="../images/res/strength-icon.png">
                    <span class="charecters">
                        <?php echo $strength; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/health-icon.png">
                    <span class="charecters">
                        <?php echo $health; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/shield-icon.png">
                    <span class="charecters"> 
                        <?php echo $defense; ?>
                    </span>
                </span>
            </div>
            <div class="exp">
                <div class="expprog" style="width: <?php echo $exp_progress ?>%;"></div>
            </div>
            <h1 class="title"><?=htmlspecialchars($monster['name'] ?? 'Неизвестный монстр') ?></h1>
            <div class="content">
                <?php if (!empty($monster)): ?>
                    <div class="character-container">
                        <img class="monster-image" src="<?= htmlspecialchars($monster['image'] ?? '') ?>" alt="<?= htmlspecialchars($monster['name'] ?? '') ?>">
                        <div class="monster-chars"> 
                            <span>
                                <img class="icons" src="../images/res/strength-icon.png">
                                <span class="charecters"><?= $monster['damage'] ?? 0 ?></span>
                            </span>
                            <span>
                                <img class="icons" src="../images/res/health-icon.png">
                                <span class="charecters"><?= $monster['hp'] ?? 0 ?></span>
                            </span>
                            <span>
                                <img class="icons" src="../images/res/shield-icon.png">
                                <span class="charecters"><?= $monster['defense'] ?? 0 ?></span>
                            </span>
                        </div>
                    </div>

                    <form id="attack-form" method="POST">
                    <input type="hidden" name="monster_id" value="<?= htmlspecialchars($monster['id'] ?? 0) ?>">
                        <button type="submit" id="attack-button">Атаковать</button>
                        <button type="button" id="surrender-button">Сдаться</button>
                    </form>
                    <script>
                        document.getElementById('surrender-button').addEventListener('click', function() {
                            window.location.href = 'monsters.php';
                        });
                    </script>
                <?php else: ?>
                    <span>Монстр не найден!</span>
                <?php endif; ?>
                <hr>
                
                <div class="character-container">
                    <?php if (!empty($user_data['avatar_path'])): ?>
                        <img class="player" src="../<?= htmlspecialchars($user_data['avatar_path']) ?>" alt="Аватарка" style="background-image: url(../images/m/ava-bg.jpg)">
                    <?php endif; ?>
                    <div class="player-chars"> 
                        <span>
                            <img class="icons" src="../images/res/strength-icon.png">
                            <span class="charecters"><?= $strength ?></span>
                        </span>
                        <span>
                            <img class="icons" src="../images/res/health-icon.png"> 
                            <span class="charecters"><?= $health ?></span>
                        </span>
                        <span>
                            <img class="icons" src="../images/res/shield-icon.png">
                            <span class="charecters"><?= $defense ?></span>
                        </span>
                    </div>
                </div>
            </div>

        
        <hr>    

        <div class="box">
            <div class="links-container">
                <a href="../user/user.php" class="link"><img style="background-image: url(../images/m/ava-bg.jpg);" src="../<?echo $user_data['avatar_path']; ?>" alt="Аватарка" id="avatar-image">
                    <?php echo $username;?>
                </a>
                <?php 
                    $links = [
                        ['href' => 'myclan.html', 'img' => 'm/clanss.png', 'text' => 'Клан'],
                        ['href' => '../index.php', 'img' => 'm/main.png', 'text' => 'Главная'],
                    ];
                    foreach ($links as $link) {
                        echo "<a href='{$link['href']}' class='link'><img src='../images/{$link['img']}' alt=''>{$link['text']}</a>";
                    }
                ?>
            </div>
        </div>

        <hr>
     
    <div class="in_all">
<span>
                    <img class="before_footer_icons" src="../images/res/up.png" alt="">
                    <span class="charecters" id="level">
                        <?php echo $user_data['lvl']; ?> ур. |
                    </span>
 
                    <img class="before_footer_icons" src="../images/res/exp.png" alt="">
                    <span class="charecters" id="experience">
                        <?php echo $exp; ?> опыта |
                    </span>

                    <img class="before_footer_icons" src="../images/res/coin.png" alt="">
                    <span class="charecters">
                        <?php echo $coin; ?> монет
                    </span>

                    <img class="before_footer_icons" src="../images/res/golds.png" alt="">
                    <span class="charecters">
                        <?php echo $gold; ?> золотых |
                    </span> 

                    <img class="before_footer_icons" src="../images/res/crystal.png" alt="">
                    <span class="charecters">
                        <?php echo $crystals; ?> кристаллов  
                    </span>
                    
                    <img class="before_footer_icons" src="../images/res/donut.png" alt="rubles">
                    <span class="charecters">
                        <?php echo $user_data['donut']; ?> рублей 
                    </span>
                </span>
    </div>
    </div>
        <footer>
            <a href="chatroom.php" class="soc">Чат</a> |
            <a href="forum.html" class="soc">Форум</a> |
            <a href="#" style="color: aquamarine; text-decoration: none;">Акция</a>

            <p>&copy; 2025, 16+ | +200% кристаллы и монеты!</p>

            <p><span id="serverTime"></span> | <a href="../logout.php" style="color: #a5a5a5; text-decoration: none;">Выход</a></p>
            
            <!-- <div class="social-icons">
                <a href="#"><img  src="../images/soc_fb1.png" alt=""></a>
                <a href="#"><img  src="../images/Inst1.jpg" alt=""></a>
                <a href="#"><img  src="../images/soc_ok1.png" alt=""></a>
                <a href="#"><img  src="../images/soc_vk1.png" alt=""></a>
            </div> -->
        </footer>
    </div>
</body>
</html>

