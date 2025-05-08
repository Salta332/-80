<?php
session_start(); // Начало сессии

require_once 'db.php'; // Установка соединения с базой данных

// Проверка, авторизован ли пользователь или он гость
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: index.php"); // Перенаправление на страницу регистрации
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

$is_guest = false; // Переменная для проверки, является ли пользователь гостем

if (!$user_data) {
    // Попробуем запросить данные о госте
    $guest_query = "SELECT * FROM guest WHERE username = ?";
    $stmt = $conn->prepare($guest_query);

    if (!$stmt) {
        die("Ошибка в подготовке запроса: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc(); // Извлечение данных гостя

    if (!$user_data) {
        die("Ошибка: не найдены данные для пользователя или гостя.");
    }

    $is_guest = true; // Устанавливаем, что это гость
}

// Обрабатываем отправку сообщения
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['msg'])) {
    $message = strip_tags(trim($_POST['msg']));
    
    // Если пользователь не гость, получаем его ID
    if (!$is_guest) {
        $userId = $user_data['id'];
        
        // Вставка сообщения
        $insert_query = "INSERT INTO messages (user_id, message) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_query);
        
        if (!$stmt) {
            die("Ошибка в подготовке запроса: " . $conn->error);
        }

        $stmt->bind_param("is", $userId, $message);
        $stmt->execute();
    }
}

// Загружаем сообщения для отображения
$stmt = $conn->query("SELECT messages.message, messages.timestamp, users.username 
                      FROM messages 
                      JOIN users ON messages.user_id = users.id 
                      ORDER BY messages.timestamp DESC");
$messages = $stmt->fetch_all(MYSQLI_ASSOC);

// Получаем текущее время на сервере
$server_time = date("H:i:s"); // Время в формате ЧЧ:ММ:СС
// Закрываем соединение
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чат</title>
    <link rel="stylesheet" href="../css/chatstyle.css">
    <link rel="stylesheet" href="../css/regstyle.css">
    <script>
        // Устанавливаем текущее время сервера
        var serverTime = new Date('1970-01-01T<?= $server_time ?>Z'); // Устанавливаем в UTC

        function updateTime() {
            // Увеличиваем время на 1 секунду
            serverTime.setSeconds(serverTime.getSeconds() + 1);
            // Обновляем элемент на странице
            document.getElementById('serverTime').innerText = serverTime.toLocaleTimeString('ru-RU', {
                timeZone: 'Asia/Novosibirsk' // Используйте временную зону для Барнаула
            });
        }

        // Обновляем время каждую секунду
        setInterval(updateTime, 1000);
    </script>
</head>
<body>
    <div class="chatbox">
        <div class="onexp">
            <img src="../images/strength.png"><span id="str"></span>
            <img src="../images/health.png"><span id="hls"></span>
            <img src="../images/defense.png"><span id="def"></span>
        </div>
        <div class="exp">
            <div class="expprog"></div>
        </div>
        <div class="locname">Беседка</div>
        <div class="spacer"></div>
        <div class="go">
            <form id="edit" method="POST">
            <div style="padding: 0; text-align: center;">
                <a href="chatroom.php" style="display: inline-block; vertical-align: top;">
                <div class="ref">
                    <img src="images/refresh.png" alt="">
                </div>
                </a>
                <input type="text" name="msg" required placeholder="Введите ваше сообщение">
                <input class="sub" type="submit" name="save" value="Отправить" >
            </div>
        </form>
        </div>
        <div class="spacer"></div>
        <div class="msgs" style="padding: 0; background: url(../images/bg5.png);">
            <?php foreach ($messages as $msg): ?>
            <div class="msgs link">
            <span style='max-width: fit-content;'>
                <?= htmlspecialchars($msg['username']) ?>: <!--Имя пользователь-->
                <?= htmlspecialchars($msg['message']) ?><!--Сообщение-->
            </span>
            <span style="font-size: 10px; color: red;">
                <?php
                    // Получаем текущее время
                    $current_time = new DateTime();
                    // Время сообщения
                    $message_time = new DateTime($msg['timestamp']);
                    // Разница между текущим временем и временем сообщения
                    $interval = $current_time->diff($message_time);
                    // Форматируем вывод времени в минутах
                    if ($interval->d > 0) {
                        echo ' (' . $interval->d . ' дн. назад)'; // Если больше суток
                    } elseif ($interval->h > 0) {
                        echo ' (' . $interval->h . ' ч. назад)'; // Если больше часа
                    } elseif ($interval->i > 0) {
                        echo ' (' . $interval->i . ' мин. назад)'; // Если меньше часа
                    } else {
                        echo ' (' . $interval->s . ' сек. назад)';
                    }
                ?>
                <div class="spacer"></div>
            </span>
            </div>
        </div>
            <?php endforeach; ?>
        </div>
        <div class="spacer"></div>
        <div class="shm">
            <a href="" class="ashow">Показать ещё</a>
        </div>
        <a href="character.html" class="link">
            <img src="../images/hero.png" alt="">Персонаж
        </a>
        <a href="myclan.html" class="link">
            <img src="../images/clan.png" alt="">Клан
        </a>
        <a href="main.php" class="link">
            <img src="../images/main.png" alt="">Главная
        </a>
        <div class="in_all_one">
            <img src="../images/up.png" alt="">
            50 ур.  
            <img src="../images/xp.png" alt="">
            10000000 / 10000000 опыт  
            <img src="../images/moneta.png" alt="">
            125g монет
            <img src="../images/gold.png" alt="">
            123456 золотых   
            <img src="../images/diamond.png" alt=""> 
            10m крысталлов  
        </div>
        <div class="footer_one">
        <a href="chatroom.php" class="ff">Чат</a>
        |
        <a href="forum.html" class="ff">Форум</a>
        |
        <a href="" class="action">Акция</a>
        <div style="padding: 3px;"></div>
        <div style="font-size: 12px; color: violet;">Всем крыссталлы и монеты +200% просто так!</div>
        <div style="padding: 3px;"></div>
        <div style="font-size: 12px;">
        <span id="serverTime"><?= $server_time ?></span> по мск
        <div style="font-size: 12px;">&copy; 2021, 16+</div>
    </div>
    <a href="logout.php" style="color: #a5a5a5;font-size: 12px;">Выход</a>
        <div style="padding-top: 15px; color: #a5a5a5;">
            <a href="#">
                <img class="soc" src="../images/soc_fb1.png" alt="">
            </a>
            <a href="#">
                <img class="soc" src="../images/Inst1.jpg" alt="">
            </a>
        <a href="#">
            <img class="soc" src="../images/soc_ok1.png" alt="">
        </a>
        <a href="#">
            <img class="soc" src="../images/soc_vk1.png" alt="">
        </a>
        </div>
    </div>
</body>
</html>