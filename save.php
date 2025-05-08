<?php
// Установка соединения с базой данных
require_once 'db.php';

// Проверяем, было ли отправлено POST-запрос
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['register'])) {
        handleRegistration();
    } elseif (isset($_POST['guest'])) {
        handleGuestLogin();
    }
}

function handleRegistration() {
    global $conn; // Используем глобальную переменную для доступа к объекту соединения

    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Проверка длины username и password
    if (strlen($username) < 4) {
        echo "<span style='color: red;'>Имя пользователя должно содержать не менее 4 символов.</span>";
        return; // Прерываем выполнение функции
    }

    if (strlen($password) < 4) {
        echo "<span style='color: red;'>Пароль должен содержать не менее 4 символов.</span>";
        return; // Прерываем выполнение функции
    }

    // Проверка существования пользователя или email с использованием подготовленных выражений
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    if (!$stmt) {
        echo "<span style='color: red;'>Ошибка SQL: " . $conn->error . "</span>";
        exit();
    }

    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $user_exists = false;

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if ($row['username'] === $username) {
                echo "<span style='color: red;'>Пользователь с таким именем уже зарегистрирован.</span>";
                $user_exists = true;
            }
            if ($row['email'] === $email) {
                echo "<span style='color: red;'>Пользователь с таким email уже зарегистрирован.</span>";
                $user_exists = true;
            }
        }
    }

    if (!$user_exists) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Хешируем пароль

        // Добавление нового пользователя с подготовленным выражением
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, strength, health, defense, lvl, exp, coin, gold, crystals) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            echo "<span style='color: red;'>Ошибка SQL: " . $conn->error . "</span>";
            exit();
        }

        // Параметры для вставки в базу данных
        $strength = 500;
        $health = 500;
        $defense = 500;

        // По умолчанию
        $lvl = 1;
        $exp = 0;
        $coin = 1000;
        $gold = 100;
        $crystals = 10;

        //$hashed_password если хешировать пароль
        $stmt->bind_param("ssssiiiiiii", $username, $email, $hashed_password, $strength, $health, $defense, $lvl, $exp, $coin, $gold, $crystals);

        $messsage ='';
        if ($stmt->execute()) {
            session_start();
            $_SESSION['username'] = $username;
            header("Location: main.php?message=Вы успешно зарегистрировались");
            exit();
        } else {
            echo "<span style='color: red;'>Ошибка: " . $stmt->error . "</span>";
        }
    }

    $stmt->close(); // Закрытие запроса
    $conn->close(); // Закрытие соединения
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Сохранение персонажа</title>
    <link rel="stylesheet" href="../css/mainstyle.css">
    <link rel="stylesheet" href="../css/chatstyle.css">
    <link rel="stylesheet" href="../css/regstyle.css">
</head>
<body>
    <div class="gamereg">
        <div class="gamename">Сохранение персонажа</div>
        <div class="spacer"></div>
        <div class="regimg">
            <div style="border: 1px solid #131313; background-color: #232323; border-radius: 3px; display: inline-block;">
                <img src="images/apsis.jpg" alt="" style="border: 1px solid #1b1b1b; border-radius: 3px;">
            </div>
        </div>
                
    </div>
        </div>

        <!-- Регистрация -->
        <div class="inputone">
            <form action="" method="POST">
                <div>* Имя персонажа:</div>
                <div>
                    <input type="text" name="username" class="inputtext">
                </div>
                <div>Email:</div>
                <div>
                    <input type="email" name="email" class="inputtext">
                </div>
                <div style="color: #a5a5a5;">* Пароль:</div>
                <div>
                    <input type="password" name="password" class="inputtext" > 
                </div>
                <div class="spacer"></div>
                <div>
                    <input type="submit" class="btn_login" name="register" value="Сохранить">
                </div>
            </form>

        </div>

        <!-- футер -->
        <div class="space" style="height: 7px; background-image:url(../images/space.jpg);"></div>
        <div class="footer_one">
        <div style="font-size: 12px; color: violet;">Всем крыссталлы и монеты +200% просто так!</div>
            <div class="spacer"></div>
            <div style="font-size: 12px;">
                <span id="serverTime"></span> по мск
            </div>
            <div style="font-size:12px;">&copy; 2021, 16+</div>
            <a class="exit" href="logout.php" style="color: #a5a5a5;">Выход</a>
        </div>
        
    </div>
</body>
</html>