<?php
session_start(); // Начало сессии

require_once 'db.php'; // Установка соединения с базой данных
require_once 'level_system.php'; 


// Проверка, авторизован ли пользователь
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: register.php"); // Перенаправление на страницу регистрации
    exit();
}

// Подготовка переменной и запрос данных пользователя
$username = $_SESSION['username'];
$user_query = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($user_query);
if (!$stmt) {
    die("Ошибка в подготовке запроса: " . $conn->error);    
}

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

// Получаем опыт для текущего и следующего уровня
$user_data = checkLevelUp($user_data, $conn);
$exp_progress = getExpProgress($user_data['lvl'], $user_data['exp']);

// Закрываем соединение
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/time.js"></script>
    <script src="js/notification.js"></script>
    <script src="js/regeneration.js"></script>
    <title>Войны Затмения</title>
    <link rel="stylesheet" href="css/all.css">
    <link rel="shortcut icon" href="images/m/game-icon.jpeg" type="image/x-icon">
</head>
<body>
    <div class="container">

        <div class="form-container">
        <div class="fix">
            <div class="onexp">
                <span class="abc">
                    <img class="icons" src="images/res/strength-icon.png">
                    <span class="charecters">
                        <?php echo $strength; ?>
                    </span>
                </span>
                <span class="abc">
                    <img class="icons" src="images/res/health-icon.png">
                    <span class="charecters">
                        <?php echo $health; ?>
                    </span>
                </span class="abc">
                <span class="abc">
                    <img class="icons" src="images/res/shield-icon.png">
                    <span class="charecters"> 
                        <?php echo $defense; ?>
                    </span>
                </span>
            </div>
            <div class="top-notif">
                <span class="right-top-notif">
                    <img class="notif-img" src="images/m/bell.png" hidden>
                    <img class="notif-img" src="images/m/bell.png" hidden>
                </span>
            </div>
        
        </div>
            <div class="exp">
                <div class="expprog" style="width: <?php echo $exp_progress ?>%;"></div>
            </div>
        
            <div class="spacer"></div>
            
            <img src="images/m/game-icon.jpeg" alt="Логотип игры" class="game-image">


            <!-- Прочие ссылки -->
            <div class="box">
                <div class="links-container">
                    <?php 
                    $links = [
                        ['href' => 'arena.php', 'img' => 'm/arena.png', 'text' => 'Арена'],
                        ['href' => 'monsters/monsters.php', 'img' => 'm/monster.png', 'text' => 'Монстры'],
                        ['href' => 'boss.html', 'img' => 'm/bosses.png', 'text' => 'Боссы'],
                        ['href' => 'hike.html', 'img' => 'm/poxod.png', 'text' => 'Поход'],
                        ['href' => 'mine.html', 'img' => 'm/shakhta.png', 'text' => 'Шахта'],
                        ['href' => 'training.html', 'img' => 'm/train.png', 'text' => 'Тренировка'],
                        ['href' => 'clans.html', 'img' => 'm/clan.png', 'text' => 'Кланы'],
                        ['href' => 'cb.html', 'img' => 'm/clan_war.png', 'text' => 'Клановые Сражения'],
                        ['href' => 'schedule.html', 'img' => 'm/schadule.png', 'text' => 'Расписание'],
                        ['href' => 'equipment.html', 'img' => 'm/equipments.png', 'text' => 'Экипировка'],
                        ['href' => 'shop.html', 'img' => 'm/shop1.png', 'text' => 'Магазин'],
                        ['href' => 'gold.php', 'img' => 'm/gold.png', 'text' => 'Купить золото'],
                        ['href' => 'tasks.html', 'img' => 'm/task.png', 'text' => 'Задания'],
                        ['href' => 'rating.html', 'img' => 'm/stat.png', 'text' => 'Лучшие'],
                        ['href' => 'bag.html', 'img' => 'm/bag.png', 'text' => 'Рюкзак'],
                        ['href' => 'chest.html', 'img' => 'm/box.png', 'text' => 'Сундук'],
                        ['href' => 'mail.html', 'img' => 'm/mail.png', 'text' => 'Почта'],
                        ['href' => 'friends.html', 'img' => 'm/friends.png', 'text' => 'Друзья'],
                    ];
                    foreach ($links as $link) {
                        echo "<a href='{$link['href']}' class='link'><img src='images/{$link['img']}' alt=''>{$link['text']}</a>";
                    }
                    ?>
                
                </div>
                <hr>
                <div class="links-container">
                        <a href="user/user.php" class="link"><img style="background-image: url(images/m/ava-bg.jpg);" src="<?php echo $avatar; ?>" alt="Аватарка" id="avatar-image">
                            <?php echo $username;?>
                        </a>
                        <?php 
                            $links = [
                                ['href' => 'myclan.html', 'img' => 'm/clanss.png', 'text' => 'Клан'],
                                ['href' => 'index.php', 'img' => 'm/main.png', 'text' => 'Главная'],
                            ];
                            foreach ($links as $link) {
                                echo "<a href='{$link['href']}' class='link'><img src='images/{$link['img']}' alt=''>{$link['text']}</a>";
                            }
                        ?>
                </div>
            </div>
            
            <hr>
            <!-- Показываем данные о пользователе или госте -->
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
        <div style="padding: 3px;"></div>
        <p>&copy; 2025, 16+ | +200% кристаллы и монеты!</p>
        <p><span id="serverTime"></span> | <a href="logout.php" style="color: #a5a5a5; text-decoration: none;">Выход</a></p>
        
        <!-- <div class="social-icons">
            <a href="#"><img  src="images/soc_fb1.png" alt=""></a>
            <a href="#"><img  src="images/Inst1.jpg" alt=""></a>
            <a href="#"><img  src="images/soc_ok1.png" alt=""></a>
            <a href="#"><img  src="images/soc_vk1.png" alt=""></a>
        </div> -->
    </footer>
    </div>
    
</body>
</html>