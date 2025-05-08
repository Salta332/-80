<?php

use BcMath\Number;

session_start(); // Начало сессии

require_once '../db.php'; // Установка соединения с базой данных
require_once '../level_system.php';
// Проверка, авторизован ли пользователь или он гость
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: ../register.php"); // Перенаправление на страницу регистрации
    exit();
}


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
    <script src="../js/time.js"></script>
    <script src="../js/regeneration.js"></script>
    <title>Войны Затмения</title>
    <link rel="stylesheet" href="../css/all.css">
    <link rel="shortcut icon" href="../images/m/game-icon.jpeg" type="image/x-icon">
    <style>
        .game-image {
        height: 100%;
        border: 2px solid gold;
        background-image: url(../images/m/ava-bg.jpg);
        }
        .title {
            text-transform: none;
        }

        @media screen and (min-width: 480px) {
            .container {
                max-width: 480px;
            }
            .main-container {
                width: 440px; /* 100% ширины для мобильных устройств */
                height: 100%; /* Можно уменьшить высоту для мобильных устройств */
                align-items: center;
                padding: none;
            }
            .links-container1 {
                width: 18%;
                height: 88%;
                gap: 18px;
                margin-bottom: 14px;
            }
            .link1 img {
                width: 100%;
                height: auto;
            }
            .logo-container {
                width: 65%;
                height: 45%;
            }
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
            <h1 class="title" >
                <?php echo $username. ', '. $user_data['lvl']. ' ур'; ?>
            </h1>
            <div class="main-container">
                <div class="links-container1">
                    <?php 
                        $links = [
                            ['href' => 'equipmets.php', 'img' => 'equip/helmet.png'],
                            ['href' => 'equipmets.php', 'img' => 'equip/shield.png'],
                            ['href' => 'equipmets.php', 'img' => 'equip/boot.png']
                        ];
                        foreach ($links as $link) {
                            echo "<a href='{$link['href']}' class='link1'><img src='../images/{$link['img']}' alt=''></a>";
                        }
                    ?> 
                </div>
                <div class="logo-container">
                    <img src="../<?php echo htmlspecialchars($user_data['avatar_path']); ?>" alt="Аватарка" class="game-image" id="avatar-image">
                    <!-- <input type="file" id="avatar-input" style="display: none;" accept="image/*">
                    <button type="button" id="upload-button">Изменить аватарку</button> -->
                </div>
                
                <div class="links-container1">
                    <?php 
                        $links = [
                            ['href' => 'equipmets.php', 'img' => 'equip/artefact.png'],
                            ['href' => 'equipmets.php', 'img' => 'equip/glove.png'],
                            ['href' => 'equipmets.php', 'img' => 'equip/ring.png']
                        ];
                        foreach ($links as $link) {
                            echo "<a href='{$link['href']}' class='link1'><img src='../images/{$link['img']}' alt=''></a>";
                        }
                    ?>
                </div>
                
            </div>

            <hr>
            <div class="chars">
                <span>
                    <img class="icons" src="../images/res/strength-icon.png">
                    <span class="charecters">
                        <?php echo 'Сила: '. $user_data['strength']; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/health-icon.png">
                    <span class="charecters">
                        <?php echo 'Здоровье: '. $user_data['max_health']; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/shield-icon.png">
                    <span class="charecters"> 
                        <?php echo 'Защита: '. $user_data['defense']; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/sum.png">
                    <span class="charecters"> 
                        <?php 
                        echo 'Всего: '. 
                        $user_data['strength'] + 
                        $user_data['max_health'] + 
                        $user_data['defense']; 
                        ?>
                    </span>
                </span>
            </div>
            <hr>
            <div class="chars"> 
                <span>
                    <img class="before_footer_icons" src="../images/res/coin.png" alt="">
                    <span class="charecters">
                        <?php echo 'Монеты: '. $coin; ?> монет
                    </span>
                </span>
                <span>
                    <img class="before_footer_icons" src="../images/res/golds.png" alt="">
                    <span class="charecters">
                        <?php echo 'Золота: '. $gold; ?> золотых 
                    </span> 
                </span> 
                <span>
                    <img class="before_footer_icons" src="../images/res/crystal.png" alt="">
                    <span class="charecters">
                        <?php echo 'Кристаллы: '. $crystals; ?> кристаллов  
                    </span>
                </span>
            </div>
            <hr>
            <div class="chars">
                <span>
                    <img class="icons" src="../images/res/about.png" alt="about">
                    <span class="charecters"> 
                        <?php echo 'О себе: '. $user_data['about']; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/gender.png" alt="gender">
                    <span class="charecters"> 
                        <?php 
                            if ($user_data['gender'] = 'м'){
                                echo 'Пол: Мужской';
                            } else {
                                echo 'Пол: Женский';
                            }
                        ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/clock.png" alt="reg">
                    <span class="charecters"> 
                        <?php echo 'Регистрация: '. $user_data['created_at']; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/id.png" alt="ID">
                    <span class="charecters"> 
                        <?php echo 'ID игрока: '. $user_data['id']; ?>
                    </span>
                </span>
            </div>
            <hr>
            <div class="chars">
                <button class="btn" type="button" onclick="window.location.href='user-rating.php';">Рейтинг игрока</button>
                <button class="btn" type="button" onclick="window.location.href='user-settings.php';">Настройки профиля</button>
            </div>
            <hr style="margin-top: 15px;">
            <!-- Прочие ссылки -->
            <div class="box">
                <div class="links-container">
                    <a href="user.php" class="link"><img style="background-image: url(../images/m/ava-bg.jpg);" src="../<?php echo $user_data['avatar_path']; ?>" alt="Аватарка" id="avatar-image">
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
        <div style="padding: 3px;"></div>
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


