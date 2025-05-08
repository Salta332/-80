<?php
// Подключение к базе данных
require_once 'db.php';

$message = "";
$messageType = ""; // "success" или "error"

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    if (!empty($name) && !empty($email)) {
        $stmt = $conn->prepare("SELECT password FROM users WHERE username = ? AND email = ?");
        $stmt->bind_param("ss", $name, $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($password);

        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            
            $subject = "Восстановление пароля";
            $emailMessage = "Ваш пароль: " . $password;
            $headers = "From: vosstanovit1234@gmail.com";

            if (mail($email, $subject, $emailMessage, $headers)) {
                header("Location: restore.php?message=" . urlencode("Пароль отправлен на вашу почту.") . "&status=success");
            } else {
                header("Location: restore.php?message=" . urlencode("Ошибка при отправке письма.") . "&status=error");
            }
        } else {
            header("Location: restore.php?message=" . urlencode("Имя персонажа и почта не совпадают!") . "&status=error");
        }
        $stmt->close();
    } else {
        header("Location: restore.php?message=" . urlencode("Пожалуйста, заполните все поля.") . "&status=error");
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/all.css">
    <link rel="shortcut icon" href="images/game-icon.jpeg" type="image/x-icon">
    <script src="js/time.js"></script>
    <script src="js/notification.js"></script>
    <title>Восстановить пароль</title>
</head>
<body>
    <div class="container">
        <h1 class="game-title">Восстановление</h1>
        <div class="form-container">
            <img src="images/m/start-image.jpeg" alt="Логотип игры" class="game-image">
            
            <?php if (isset($_GET['message']) && isset($_GET['status'])): ?>
                <div class="message-container <?php echo htmlspecialchars($_GET['status']) === 'error' ? 'error-message' : 'success-message'; ?>" id="messageContainer">
                    <?php echo htmlspecialchars($_GET['message']); ?>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                <input type="text" name="name" placeholder="Имя персонажа *">
                <input type="email" name="email" placeholder="Email *">
                <button type="submit" class="btn">Восстановить</button>
            </form>
            <hr>
            <li type="none"><a href="restore.php">Регистрация</a></li>
            <li type="none"><a href="login.php">Войти</a></li>
            
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

