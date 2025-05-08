<!-- // monsters.php -->
<?php
require '../db.php'; // Подключение к БД
require_once '../level_system.php';

// Проверка, авторизован ли пользователь или он гость
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: ../register.php"); // Перенаправление на страницу регистрации
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc(); // Извлечение данных

// Получаем опыт для текущего и следующего уровня
$user_data = checkLevelUp($user_data, $conn);
$exp_progress = getExpProgress($user_data['lvl'], $user_data['exp']);

// Получение фильтра по редкости
$rarity_filter = isset($_GET['rarity']) ? $_GET['rarity'] : 'common';

// SQL-запрос с фильтрацией
$sql = "SELECT id, name, image, damage, hp, defense, rarity FROM monsters WHERE rarity = '" . mysqli_real_escape_string($conn, $rarity_filter) . "'";
$result = $conn->query($sql);

function getLastAttackTime($conn, $username, $monster_id) {
    $query = $conn->prepare("SELECT last_attack FROM attack_limits WHERE username = ? AND monster_id = ?");
    $query->bind_param("si", $username, $monster_id);
    $query->execute();
    $result = $query->get_result();
    
    return $result->num_rows > 0 ? strtotime($result->fetch_assoc()['last_attack']) : 0;
}
// Пример обновления времени последней атаки в базе данных
$stmt = $conn->prepare("UPDATE attack_limits SET last_attack = NOW() WHERE username = ? AND monster_id = ?");
$stmt->bind_param("si", $username, $monster_id);
$stmt->execute();

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
</head>
<body>
    <!-- Модальное окно -->
    <div id="battle-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="battle-title"></h2>
            <p id="battle-message"></p>
            <button id="toggle-log" class="attack-button" style="display: none;">Лог боя</button>
            <div id="battle-log" style="display: none; margin-top: 10px; border-top: 1px solid #fff; padding-top: 10px;"></div>
        </div>
    </div>

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
        <h1 class="title">Список монстров</h1>
        
        <div class="tab-container">
            <div class="tab <?= $rarity_filter == 'common' ? 'active' : '' ?>" onclick="filterRarity('common')">Обычный</div>
            <div class="tab <?= $rarity_filter == 'rare' ? 'active' : '' ?>" onclick="filterRarity('rare')">Редкий</div>
            <div class="tab <?= $rarity_filter == 'epic' ? 'active' : '' ?>" onclick="filterRarity('epic')">Эпический</div>
        </div>
        <div id="battle-result"></div>
        <div class="monsters-grid">
            <?php while ($monster = $result->fetch_assoc()):
                $last_attack_time = getLastAttackTime($conn, $_SESSION['username'], $monster['id']);
            ?>
            <div class="monster-card <?= $monster['rarity'] ?>">
                <div class="monster">
                    <img class="monster-image" src="<?= htmlspecialchars($monster['image']) ?>" alt="<?= htmlspecialchars($monster['name']) ?>">
                    <p class="monster-name"><?= htmlspecialchars($monster['name']) ?></p>
                </div>
                <div class="monster char">
                    <p>
                        <img class="icons" src="../images/res/strength-icon.png"> 
                        <span class="character"><?= $monster['damage'] ?></span>
                    </p>
                    <p>
                        <img class="icons" src="../images/res/health-icon.png"> 
                        <span class="character"><?= $monster['hp'] ?></span>
                    </p>
                    <p>
                        <img class="icons" src="../images/res/shield-icon.png"> 
                        <span class="character"><?= $monster['defense'] ?></span>
                    </p>
                    <button class="attack-button" 
                        data-monster-id="<?= intval($monster['id']) ?>" 
                        data-rarity="<?= htmlspecialchars($monster['rarity']) ?>"
                        data-last-attack="<?= $last_attack_time ?>">
                        Атаковать
                    </button>

                    <div class="timer"></div>
                </div>
            </div>
            <?php endwhile; ?>

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

        <footer>
            <a href="chatroom.php" class="soc">Чат</a> |
            <a href="forum.html" class="soc">Форум</a> |
            <a href="#" style="color: aquamarine; text-decoration: none;">Акция</a>

            <p>&copy; 2025, 16+ | +200% кристаллы и монеты!</p>

            <p><span id="serverTime"></span> | <a href="../logout.php" style="color: #a5a5a5; text-decoration: none;">Выход</a></p>
            
            <div class="social-icons">
                <a href="#"><img  src="../images/soc_fb1.png" alt=""></a>
                <a href="#"><img  src="../images/Inst1.jpg" alt=""></a>
                <a href="#"><img  src="../images/soc_ok1.png" alt=""></a>
                <a href="#"><img  src="../images/soc_vk1.png" alt=""></a>
            </div>
        </footer>
    </div>
</body>
</html>

<script>

    // Функция для определения времени ожидания (cooldown) в секундах по редкости
// Функция для получения времени отката атаки в секундах по редкости
function getCooldown(rarity) {
    switch (rarity) {
        case 'common': return 4 * 60 * 60;  // 4 часа
        case 'rare': return 6 * 60 * 60;   // 6 часов
        case 'epic': return 10 * 60 * 60;  // 10 часов
        default: return 4 * 60 * 60;
    }
}

// Восстанавливаем таймер при загрузке страницы
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('.attack-button').forEach(button => {
        const monsterId = button.getAttribute('data-monster-id');
        const rarity = button.getAttribute('data-rarity');
        const lastAttackTime = localStorage.getItem(`lastAttack_${monsterId}`) || 0;
        const cooldown = getCooldown(rarity);
        const currentTime = Math.floor(Date.now() / 1000);
        const timeSinceLastAttack = currentTime - lastAttackTime;
        const timerElement = button.closest('.monster-card').querySelector('.timer');

        if (timeSinceLastAttack < cooldown) {
            const remainingTime = cooldown - timeSinceLastAttack;
            startCountdown(timerElement, remainingTime, button);
        } else {
            timerElement.innerText = "Готов к атаке!";
            button.disabled = false;
        }
    });
});

// Обработчик нажатия кнопки атаки
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('.attack-button').forEach(button => {
        const monsterId = button.getAttribute('data-monster-id');
        const rarity = button.getAttribute('data-rarity');
        const cooldown = getCooldown(rarity);
        const lastAttackTime = localStorage.getItem(`lastAttack_${monsterId}`) || 0;
        const currentTime = Math.floor(Date.now() / 1000);
        const timeSinceLastAttack = currentTime - lastAttackTime;
        const timerElement = button.closest('.monster-card').querySelector('.timer');

        if (timeSinceLastAttack < cooldown) {
            const remainingTime = cooldown - timeSinceLastAttack;
            startCountdown(timerElement, remainingTime, button);
        } else {
            timerElement.innerText = "Готов к атаке!";
            button.disabled = false;
        }
    });
});

document.addEventListener('click', function (event) {
    if (event.target.classList.contains('attack-button')) {
        const button = event.target;
        const monsterCard = button.closest('.monster-card');
        if (!monsterCard) return;

        const monsterId = button.getAttribute('data-monster-id');
        const rarity = button.getAttribute('data-rarity');
        const cooldown = getCooldown(rarity);
        const lastAttackTime = parseInt(button.getAttribute('data-last-attack'), 10) || 0;
        const currentTime = Math.floor(Date.now() / 1000);
        const timeSinceLastAttack = currentTime - lastAttackTime;
        const timerElement = monsterCard.querySelector('.timer');

        if (timeSinceLastAttack < cooldown) {
            const remainingTime = cooldown - timeSinceLastAttack;
            startCountdown(timerElement, remainingTime, button);
            button.disabled = true;
            return;
        }

        fetch('battle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ monster_id: monsterId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'win') {
                const attackTime = Math.floor(Date.now() / 1000);
                localStorage.setItem(`lastAttack_${monsterId}`, attackTime);
                button.setAttribute('data-last-attack', attackTime);
                startCountdown(timerElement, cooldown, button);
                button.disabled = true;
            }
        })
        .catch(error => console.error("Ошибка:", error));
    }
});

function startCountdown(element, duration, button) {
    let remainingTime = duration;
    element.innerText = formatTime(remainingTime);
    button.disabled = true;
    const interval = setInterval(() => {
        remainingTime--;
        element.innerText = formatTime(remainingTime);
        if (remainingTime <= 0) {
            clearInterval(interval);
            element.innerText = "Готов к атаке!";
            button.disabled = false;
        }
    }, 1000);
}

function formatTime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${h}ч ${m}м ${s}с`;
}

function getCooldown(rarity) {
    switch (rarity) {
        case 'common': return 4 * 60 * 60;
        case 'rare': return 6 * 60 * 60;
        case 'epic': return 10 * 60 * 60;
        default: return 4 * 60 * 60;
    }
}


// <span class='characters'>
</script>

<style>

    /* ленточка */
    .timer {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255, 0, 0, 0.8);
    color: #fff;
    font-size: 16px;
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 5px;
    text-align: center;
}
.monster-card {
    background: rgba(255, 255, 255, 0.1);
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    transition: 0.3s;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
    position: relative; /* Для позиционирования таймера */
    overflow: hidden; /* Чтобы лента не выходила за пределы карточки */
}


</style>