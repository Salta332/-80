<!-- Eclipse Wars - Войны Затмения -->
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/all.css">
    <link rel="shortcut icon" href="images/game-icon.jpeg" type="image/x-icon">
    <script src="js/onclick.js"></script>
    <title>Войны Затмения</title> 
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h1 class="game-title">Войны Затмения</h1>
            <img src="images/m/start-image.jpeg" alt="Логотип игры" class="game-image">
            <p class="tagline">
            Мир, погруженный во тьму, где солнце больше не восходит, а луна скрыта завесой вечного затмения. 
            Цивилизации пали, оставив после себя лишь руины и воспоминания о былой славе. 
            Но даже в этой тьме есть те, кто готов бороться за выживание, власть и надежду на новый рассвет.
            </p>
            <p class="tagline">
            В <b>Войны Затмения</b> вы станете частью этого мрачного мира, где каждый день — это борьба за ресурсы, территории и влияние. 
            Создайте своего героя, прокачивайте его навыки, сражайтесь с монстрами, боссами и другими игроками, чтобы доказать, 
            что именно вы достойны стать повелителем этого мира.
            </p>
            <div class="buttons">
                <button name="register" class="btn" onclick="window.location.href='register.php';">Зарегистрироваться</button>
                <button name="login" class="btn" onclick="window.location.href='index.php';">Продолжить</button>
            </div>
            
        </div>
        <footer>
            <p>&copy; 2025, 16+</p>
            <!-- <div class="social-icons">
                <a href="#"><img src="images/soc_fb1.png" alt="Facebook"></a>
                <a href="#"><img src="images/Inst1.jpg" alt="Instagram"></a>
                <a href="#"><img src="images/soc_vk1.png" alt="VK"></a>
            </div> -->
        </footer>
    </div>

</body>
</html>
<style>
    h1 {
        margin-top: -5px;
    }
    .tagline{
        text-align: justify;
        font-size: 16px;
    }
</style>