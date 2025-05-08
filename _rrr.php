<?php
require_once 'db.php';

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

    $default_avatar = ($gender === 'ж') ? '../images/m/default-avatar-f.png' : '../images/m/default-avatar-m.png';

    if (empty($username) || empty($password)) {
        header("Location: register.php?error=" . urlencode("Поле с * не должны быть пустыми!"));
        exit();
    }

    if (strlen($username) < 4 || strlen($password) < 4) {
        header("Location: register.php?error=" . urlencode("Имя пользователя и пароль должны содержать минимум 4 символа!"));
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: register.php?error=" . urlencode("Пользователь с таким именем или email уже существует!"));
        exit();
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT); # Хеширование пароля
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, strength, health, defense, lvl, exp, coin, gold, crystals, avatar_path) 
                            VALUES (?, ?, ?, 500, 500, 500, 1, 0, 1000, 100, 10, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $default_avatar); # password -> $hashed_password - Для хеширования 
    

    if ($stmt->execute()) {
        session_start();
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit();
    } else {
        header("Location: register.php?error=" . urlencode("Ошибка регистрации. Попробуйте снова!"));
        exit();
    }
}

function handleLogin() {
    global $conn;

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        header("Location: login.php?error=" . urlencode("Имя пользователя и пароль обязательны!"));
        exit();
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: login.php?error=" . urlencode("Такой пользователь не зарегистрирован!"));
        exit();
    }

    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        session_start();
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit();
    } else {
        header("Location: login.php?error=" . urlencode("Неправильный пароль!"));
        exit();
    }
}


// ?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <link rel="shortcut icon" href="images/game-icon.jpeg" type="image/x-icon">
    <script src="js/time.js"></script>
    <script src="js/reg_notif.js"></script>
    <title>Войны Затмения - Регистрация</title> 
</head>
<body>
    <div class="container">
        
        <div class="form-container">
            <h1 class="game-title">Войны Затмения</h1>
            <img src="images/m/start-image.jpeg" class="game-image">
            <p class="tagline">
            В мире, где царит вечное затмение, только сильнейшие выживают. 
            Ты готов доказать, что достоин стать легендой? 
            Присоединяйся к <b>Войны Затмения</b> и начни свой путь к славе!
            </p>
            <form action="register.php" method="POST">
                <input type="text" name="username" placeholder="Имя персонажа *" >
                <input type="email" name="email" placeholder="Email">
                <input type="password" name="password" placeholder="Пароль *" >
                <button type="submit" name="register" class="btn">Зарегистрироваться</button>
                <button type="submit" name="login" class="btn btn-secondary">Войти</button>
            
            </form>
        </div>
        <footer>
            <p>&copy; 2025, 16+ | +200% кристаллы и монеты!</p>
            <!-- <p><span id="serverTime"></span></p> -->
            <div class="social-icons">
                <a href="#"><img src="images/soc_fb1.png" alt="Facebook"></a>
                <a href="#"><img src="images/Inst1.jpg" alt="Instagram"></a>
                <a href="#"><img src="images/soc_vk1.png" alt="VK"></a>
            </div>
        </footer>
    </div>

</body>
</html>

<style>
    /* .tagline{
        text-align: justify;
        font-size: 16px;
    }     */
    input {
    width: 93%;
    padding: 10px;
    margin: 8px 0;
    border: 1px solid #ff0044;
    background: #222;
    color: #fff;
    border-radius: 5px;
    }
    .notification {
    position: fixed;
    top: -50px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 0, 68, 0.9);
    color: white;
    padding: 15px 25px;
    border-radius: 5px;
    box-shadow: 0 4px 10px rgba(255, 0, 68, 0.6);
    font-size: 16px;
    font-weight: bold;
    text-align: center;
    opacity: 0;
    transition: top 0.5s ease, opacity 0.5s ease;
    z-index: 9999;
    }

    .notification.show {
        top: 20px;
        opacity: 1;
    }

    .notification.hide {
        top: -50px;
        opacity: 0;
    }
    
</style>
