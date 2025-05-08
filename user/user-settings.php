<?php
session_start(); // Начало сессии

require_once '../db.php'; // Установка соединения с базой данных

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



// Закрываем соединение
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="../js/time.js"></script>
    <script src="../js/change-avatar.js"></script>
    <title>Войны Затмения</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/style_2.css">
    <link rel="shortcut icon" href="../images/res/game-icon.jpeg" type="image/x-icon">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="onexp">
                <span>
                    <img class="icons" src="../images/res/strength-icon.png">
                    <span class="charecters">
                        <?php echo htmlspecialchars($user_data['strength']); ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/health-icon.png">
                    <span class="charecters">
                        <?php echo htmlspecialchars($user_data['health']); ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/shield-icon.png">
                    <span class="charecters"> 
                        <?php echo htmlspecialchars($user_data['defense']); ?>
                    </span>
            </span>
            </div>
            <div class="exp">
                <div class="expprog"></div>
            </div>
            <h1 class="title" >
                Настройки
            </h1>
            <div class="main-container">
                <div class="logo-container">
                    <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Аватарка" class="game-image" id="avatar-image">
                    <input type="file" id="avatar-input" style="display: none;" accept="image/*">
                    <button type="button" id="upload-button">Изменить аватарку</button>
                </div>
            </div>
            
            <label for="gender">Пол:</label>
            <select name="gender" id="gender" required>
                <option value="м">Мужской</option>
                <option value="ж">Женский</option>
            </select>
            <hr>
            <!-- Прочие ссылки -->
            <div class="box">
                <div class="links-container">
                    <?php 
                    $links = [
                        ['href' => 'user.php', 'img' => 'm/man.png', 'text' => $username],
                        ['href' => 'myclan.html', 'img' => 'm/clan.png', 'text' => 'Клан'],
                        ['href' => '../index.php', 'img' => 'm/main.png', 'text' => 'Главная'],
                    ];
                    foreach ($links as $link) {
                        echo "<a href='{$link['href']}' class='link'><img src='../images/{$link['img']}' alt=''>{$link['text']}</a>";
                    }
                    ?>
                </div>
            </div>
            
            <hr>
            <!-- Показываем данные о пользователе или госте -->
            <div class="in_all">
                <span>
                    <img class="before_footer_icons" src="../images/res/up.png" alt="">
                    <span class="charecters">
                        <?php echo htmlspecialchars($user_data['lvl']); ?> ур. |
                    </span>
                </span>  
                <span>
                    
                    <img class="before_footer_icons" src="../images/res/exp.png" alt="">
                    <span class="charecters">
                        <?php echo htmlspecialchars($user_data['exp']); ?> опыта |
                    </span>
                </span>  
                <span>
                    <img class="before_footer_icons" src="../images/res/coin.png" alt="">
                    <span class="charecters">
                        <?php echo htmlspecialchars($user_data['coin']); ?> монет |
                    </span>
                </span>
                <span>
                    <img class="before_footer_icons" src="../images/res/golds.png" alt="">
                    <span class="charecters">
                        <?php echo htmlspecialchars($user_data['gold']); ?> золотых |
                    </span> 
                </span>
                <span>
                    <img class="before_footer_icons" src="../images/res/crystal.png" alt="">
                    <span class="charecters">
                        <?php echo htmlspecialchars($user_data['crystals']); ?> крысталлов  
                    </span>
                </span>
            </div>

        </div>
    <footer>
        <a href="../chatroom.php" class="soc">Чат</a> |
        <a href="forum.html" class="soc">Форум</a> |
        <a href="#" style="color: aquamarine; text-decoration: none;">Акция</a>
        <div style="padding: 3px;"></div>
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

<style>
    #upload-button {
    margin-top: 10px; /* Отступ от аватарки */
    padding: 5px 10px; /* Внутренние отступы */
    cursor: pointer; /* Курсор в виде указателя */
    background-color: #ff3366; /* Цвет кнопки */
    color: white; /* Цвет текста */
    border: none; /* Убираем рамку */
    border-radius: 5px; /* Закругляем углы */
    }

    .main-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    }

    .links-container1 {
        display: flex;
        flex-direction: column;
        gap: 10px; /* Расстояние между картинками */

    }

    .link1 {
        border-radius: 3px;
        border: ridge;
    }

    .link1 img {
        width: 50px; /* Размер картинок */
        height: 50px;
    }

    .game-image {
    width: 90%;
    height: 90%;
    border-radius: 85%;
    cursor: pointer;
    border: 2px solid #ccc;
    }
    .charecters {
        position: relative;
        bottom: 3px;
    }
    .exp {
        margin-top: 15px;
    }
    .expprog {
        height: 5px;
        background-color:rgb(15, 200, 0);
        border-radius: 3px;
        box-shadow: 0px 0px 2px rgba(0, 255, 17, 0.6);
    }
    .before_footer_icons {
        width: 18px;
        height: 18px;
    }
    .icons {
        width: 20px;
        height: 20px;
    }
    .soc{
        text-decoration: none;
        color: #ff3366;
    }
        .links-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 10px;
            text-align: center;

        }
        .link {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: white;
            background: rgba(0, 0, 0, 0.6);
            padding: 10px;
            border-radius: 8px;
            transition: 0.3s;
        }
        .link img {
            width: 50px;
            height: 50px;
            margin-bottom: 5px;
            border-radius: 10px;
        }
        .link:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            margin: 0;
        }
        .onexp {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10px, 1fr));
            text-align: center;
        }
        .title {
            font-size: 20px;
            color: #ff3366;
            text-decoration: a;
        }
    </style>
