<?php
// Установка соединения с базой данных
require_once 'db.php';

$message = "";
$messageType = ""; // "success" или "error"

// Проверяем, было ли отправлено POST-запрос
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    handleLogin();
}

function handleLogin() {
    global $conn;

    $username = trim($_POST['name']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        header("Location: login.php?message=" . urlencode("Пожалуйста, заполните все поля.") . "&status=error");
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    if (!$stmt) {
        header("Location: login.php?message=" . urlencode("Ошибка SQL: " . $conn->error) . "&status=error");
        exit();
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();

    if ($user_data) {
        if ($password === $user_data['password']) {
            session_start();
            $_SESSION['username'] = $username;
            header("Location: index.php");
            exit();
        } else {
            header("Location: login.php?message=" . urlencode("Неверный пароль!") . "&status=error");
            exit();
        }
    } else {
        header("Location: login.php?message=" . urlencode("Пользователь не найден!") . "&status=error");
        exit();
    }
}

?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/all.css">
    <link rel="shortcut icon" href="images/game-icon.jpeg" type="image/x-icon">
    <script src="js/time.js"></script>
    <script src="js/notification.js"></script>
    <title>Войны Затмения – Вход</title>
</head>


<body>
    <div class="container">
        <h1 class="game-title">Авторизация</h1>
        <div class="form-container">
            <img src="images/m/start-image.jpeg" class="game-image">
            <p class="tagline">
                Тьма поглотила мир, но твой свет еще горит. Войди и продолжи свой путь к победе.
            </p>
            <?php if (isset($_GET['message']) && isset($_GET['status'])): ?>
                <div class="message-container <?php echo htmlspecialchars($_GET['status']) === 'error' ? 'error-message' : 'success-message'; ?>" id="messageContainer">
                    <?php echo htmlspecialchars($_GET['message']); ?>
                </div>
            <?php endif; ?>
            <form action="login.php" method="post">
                <input type="text" name="name" placeholder="Имя персонажа *">
                <input type="password" name="password" placeholder="Пароль *">
                <button type="submit" class="btn" name="login">Войти в игру</button>
            </form>
            <hr>
            <li type="none"><a href="restore.php">Забыл пароль</a></li>
            <li type="none"><a href="register.php">Регистрация</a></li>
            
        </div>

        <footer>
            <p>&copy; 2025, 16+ | +200% кристаллы и монеты!</p>
            <p>Время: <span id="serverTime"></span> по мск</p>
            <!-- <div class="social-icons">
                <a href="#"><img src="../images/soc_fb1.png" alt="Facebook"></a>
                <a href="#"><img src="../images/Inst1.jpg" alt="Instagram"></a>
                <a href="#"><img src="../images/soc_vk1.png" alt="VK"></a>
            </div> -->
        </footer>
    </div>
</body>
</html>