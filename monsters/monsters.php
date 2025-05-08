<?php
// monsters.php
require '../db.php';
require_once '../level_system.php';
require_once 'functions.php';

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../register.php");
    exit();
}

$victory_message = '';
if (isset($_GET['victory'])) {
    $reward_message = isset($_GET['reward']) ? urldecode($_GET['reward']) : 'Награда получена!';
    
    $victory_message = '<div class="victory-notice" style="
        background: rgba(0, 200, 0, 0.2);
        border: 1px solid #0f0;
        padding: 10px;
        border-radius: 5px;
        margin: 10px 0;
        text-align: center;
        color: #0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    ">
        <img src="../images/m/victory.jfif" style="width: 40px; height: 40px;">
        <span>'.htmlspecialchars($reward_message).'</span>
    </div>';
}

$username = $_SESSION['username'];

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

// Обновление уровня
$user_data = checkLevelUp($user_data, $conn);
$exp_progress = getExpProgress($user_data['lvl'], $user_data['exp']);

// Фильтрация
$rarity_filter = $_GET['rarity'] ?? 'common';
$allowed_rarities = ['common', 'rare', 'epic'];
if (!in_array($rarity_filter, $allowed_rarities)) {
    $rarity_filter = 'common';
}

// Получение монстров с учетом кулдаунов
$stmt = $conn->prepare("
    SELECT m.*, 
    IFNULL(pc.cooldown_end, 0) as cooldown_end 
    FROM monsters m
    LEFT JOIN player_cooldowns pc 
        ON m.id = pc.monster_id 
        AND pc.username = ?
    WHERE m.rarity = ?
");

$stmt->bind_param("ss", $username, $rarity_filter);
$stmt->execute();
$result = $stmt->get_result();
// После получения данных пользователя

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Монстры</title>
    <link rel="stylesheet" href="../css/all.css">
    <script src="../js/time.js"></script>
    <script src="attack.js"></script>
    <script src="../js/regeneration.js"></script>
    <style>

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
                <?php if (isset($_SESSION['level_up_message'])): ?>
                    <div class="level-up-notification">
                        <?php echo $_SESSION['level_up_message']; ?>
                    </div>
                    <?php unset($_SESSION['level_up_message']); ?>
                <?php endif; ?>
                
                <?php echo $victory_message; ?>

                <?php if (isset($_GET['defeat'])): ?>
                    <div class="defeat-notification">
                        <img src="../images/m/lose.jfif" style="width: 40px; height: 40px;">
                        <?php echo htmlspecialchars($_GET['message'] ?? 'Поражение!'); ?>
                    </div>
                <?php endif; ?>
            <h1 class="title">Монстры</h1>

            <div class="tab-container">
                <div class="tab <?= $rarity_filter == 'common' ? 'active' : '' ?>" onclick="filterRarity('common')">Обычный</div>
                <div class="tab <?= $rarity_filter == 'rare' ? 'active' : '' ?>" onclick="filterRarity('rare')">Редкий</div>
                <div class="tab <?= $rarity_filter == 'epic' ? 'active' : '' ?>" onclick="filterRarity('epic')">Эпический</div>
            </div>
            
            <div class="monsters-grid">
                <?php 
                if ($result->num_rows > 0) {
                    $monsters = [];
                    while ($monster = $result->fetch_assoc()) {
                        $monsters[] = $monster;
                    }
                    
                    // Выводим монстров
                    if (!empty($monsters)) {
                        foreach ($monsters as $monster):
                            $cooldown = $monster['cooldown_end'] ?? 0;
                            $remaining = max(0, $cooldown - time());
                            $can_attack = $remaining <= 0;
                ?>
                <div class="monster-card <?= !$can_attack ? 'disabled' : '' ?>">
                    <?php if ($can_attack): ?>
                        <a class="m" href="monster.php?id=<?= $monster['id'] ?>">
                    <?php endif; ?>
                    
                        <img class="monster-image" src="<?= htmlspecialchars($monster['image']) ?>" 
                            alt="<?= htmlspecialchars($monster['name']) ?>">
                        <?= htmlspecialchars($monster['name']) ?>
                    
                    <?php if ($can_attack): ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!$can_attack): ?>
                        <div class="cooldown-timer" 
                            id="timer-<?= $monster['id'] ?>">
                            <?= gmdate("H:i:s", $remaining) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php 
                    endforeach;
                }
                } else {
                    echo "<p>Монстров этой категории пока нет!</p>";
                }

            ?>

        </div>
        
        <hr>    

        <div class="box">
            <div class="links-container">
                <a href="../user/user.php" class="link"><img style="background-image: url(../images/m/ava-bg.jpg);" src="../<?php echo $avatar ?>" alt="Аватарка" id="avatar-image">
                    <?php echo $username; ?>
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

    <script>
        // Таймеры для всех активных кулдаунов
        <?php 
        foreach ($monsters as $monster): 
            $remaining = max(0, $monster['cooldown_end'] - time());
            if ($remaining > 0): 
        ?>
            initTimer(<?= $remaining ?>, 'timer-<?= $monster['id'] ?>');
        <?php 
            endif;
        endforeach; 
        ?>
    </script>

</body>
</html>

<script>
function initTimer(seconds, elementId, monsterId) {
    var timerElement = document.getElementById(elementId);
    if (!timerElement) return;

    var interval = setInterval(function() {
        seconds--;
        if (seconds <= 0) {
            clearInterval(interval);
            // Удаляем кулдаун через AJAX
            removeCooldown(monsterId);
            return;
        }
        
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;
        
        timerElement.textContent =  
            
            (hours > 0 ? hours + ':' : '') +
            (minutes < 10 ? '0' : '') + minutes + ':' + 
            (secs < 10 ? '0' : '') + secs;
    }, 1000);
}

function removeCooldown(monsterId) {
    fetch('remove_cooldown.php?monster_id=' + monsterId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Обновляем интерфейс
                var card = document.querySelector('.monster-card.disabled [id="timer-' + monsterId + '"]').closest('.monster-card');
                card.classList.remove('disabled');
                card.querySelector('a').style.pointerEvents = 'auto';
                card.querySelector('.cooldown-timer').remove();
            }
        });
}

// Инициализация таймеров
<?php 
foreach ($monsters as $monster): 
    $remaining = max(0, $monster['cooldown_end'] - time());
    if ($remaining > 0): 
?>
    initTimer(<?= $remaining ?>, 'timer-<?= $monster['id'] ?>', <?= $monster['id'] ?>);
<?php 
    endif;
endforeach; 
?>

function filterRarity(rarity) {
            window.location.href = `monsters.php?rarity=${rarity}`;
        }
</script>
    
