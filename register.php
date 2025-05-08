<?php
require_once 'db.php';

$message = "";
$messageType = ""; // "success" или "error"


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        handleRegistration();
    } elseif (isset($_POST['login'])) {
        handleLogin();
    }
}


function handleRegistration() {
    global $conn;

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $gender = 'м';

    $default_avatar = ($gender === 'ж') ? 'images/avatars/default-avatar-f.png' : 'images/avatars/default-avatar-m.png';

    // Проверка обязательных полей (username и password)    
    if (empty($username) || empty($password)) {
        header("Location: register.php?message=" . urlencode("Имя пользователя и пароль должны быть заполнены!") . "&status=error");
        exit();
    }

    // Проверка длины username и password
    if (strlen($username) < 4 || strlen($password) < 4) {
        header("Location: register.php?message=" . urlencode("Имя пользователя и пароль должны содержать минимум 4 символа!") . "&status=error");
        exit();
    }
    
    // Проверка, существует ли пользователь с таким username
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: register.php?message=" . urlencode("Пользователь с таким именем уже существует!") . "&status=error");
        exit();
    }

    // Если email не пустой, проверяем, не занят ли он
    if (!empty($email)) {
        if ($result->num_rows > 0) {
            header("Location: register.php?message=" . urlencode("Пользователь с таким email уже существует!") . "&status=error");
            exit();
        }
    }

    // Если email пустой, передаем NULL в базу данных
    $email = empty($email) ? null : $email;

    // Вставка данных в базу (без хеширования пароля)
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, strength, health, defense, lvl, exp, coin, gold, crystals, avatar_path) 
                            VALUES (?, ?, ?, 500, 500, 500, 1, 0, 1000, 100, 10, ?)");
    $stmt->bind_param("ssss", $username, $email, $password, $default_avatar); // Пароль сохраняется в открытом виде

    if ($stmt->execute()) {
        session_start();
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit();
    } else {
        header("Location: register.php?message=" . urlencode("Ошибка регистрации. Попробуйте снова!") . "&status=error");
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
    <title>Войны Затмения - Регистрация</title> 
</head>
<body>
    <div class="container">
        <h1 class="game-title">Регистрация</h1>
        <div class="form-container">
            <img src="images/m/start-image.jpeg" class="game-image">
            <p class="tagline">
            В мире, где царит вечное затмение, только сильнейшие выживают. 
            Ты готов доказать, что достоин стать легендой? 
            Присоединяйся к <b>Войны Затмения</b> и начни свой путь к славе!
            </p>
            <form action="register.php" method="POST">
                <!-- Контейнер для сообщений -->
                <?php if (isset($_GET['message']) && isset($_GET['status'])): ?>
                    <div class="message-container <?php echo htmlspecialchars($_GET['status']) === 'error' ? 'error-message' : 'success-message'; ?>" id="messageContainer">
                        <?php echo htmlspecialchars($_GET['message']); ?>
                    </div>
                <?php endif; ?>
                <input type="text" name="username" placeholder="Имя персонажа *" >
                <input type="email" name="email" placeholder="Email">
                <input type="password" name="password" placeholder="Пароль *" >
                <button type="submit" name="register" class="btn">Зарегистрироваться</button>
                
            </form>
            <li type="none" style="margin-top: 10px;"><a href="login.php">Войти</a></li>
        </div>
        <footer>
            <p>&copy; 2025, 16+ | +200% кристаллы и монеты!</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const messageContainer = document.getElementById('messageContainer');
            if (messageContainer) {
                messageContainer.style.display = 'block';
            }
        });
    </script>
</body>
</html>