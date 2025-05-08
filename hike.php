<?php
session_start();
require_once 'db.php';
require_once 'level_system.php';

if (!isset($_SESSION['username'])) {
    header("Location: register.php");
    exit();
}

$username = $_SESSION['username'];
$error = '';
$success = '';

// Получаем данные пользователя
$user_query = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$user_data = processHealthRegeneration($user_data, $conn);

// Обработка обменов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    
    try {
        if (isset($_POST['exchange_crystals'])) {
            // Обмен кристаллов на золото: 1 кристалл = 10 золота
            $crystal = (int)$_POST['crystals'];
            $to_gold = $crystal * 10;
            
            if ($user_data['crystals'] < $crystal) {
                throw new Exception("Недостаточно кристаллов!");
            }
            
            $stmt = $conn->prepare("UPDATE users SET crystals = crystals - ?, gold = gold + ? WHERE username = ?");
            $stmt->bind_param("iis", $crystal, $to_gold, $username);
            $stmt->execute();
            
            $success = "Успешно обменяно $crystal кристаллов на $to_gold золота!";
        }
        elseif (isset($_POST['buy_gold'])) {
            // Покупка золота за рубли: 10 золота = 1 рубль
            $to_gold = (int)$_POST['gold'];
            $donut = ceil($to_gold / 10); // Округляем вверх
            
            if ($user_data['donut'] < $donut) {
                throw new Exception("Недостаточно рублей!");
            }
            
            $stmt = $conn->prepare("UPDATE users SET gold = gold + ?, donut = donut - ? WHERE username = ?");
            $stmt->bind_param("iis", $to_gold, $donut, $username);
            $stmt->execute();
            
            $success = "Успешно куплено $to_gold золота за $donut рублей!";
        }
        elseif (isset($_POST['buy_crystals'])) {
            // Покупка кристаллов за рубли: 1 кристалл = 1 рубль
            $crystal = (int)$_POST['crystals'];
            $donut = $crystal;
            
            if ($user_data['donut'] < $donut) {
                throw new Exception("Недостаточно рублей!");
            }
            
            $stmt = $conn->prepare("UPDATE users SET crystals = crystals + ?, donut = donut - ? WHERE username = ?");
            $stmt->bind_param("iis", $crystal, $donut, $username);
            $stmt->execute();
            
            $success = "Успешно куплено $crystal кристаллов за $donut рублей!";
        }
        
        $conn->commit();
        // Обновляем данные пользователя после обмена
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

$user_data = checkLevelUp($user_data, $conn);
$exp_progress = getExpProgress($user_data['lvl'], $user_data['exp']);


?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="js/time.js"></script>
    <script src="js/notification.js"></script>
    <script src="js/regeneration.js"></script>
    <title>Покупка Золота Войны Затмения</title>
    <link rel="stylesheet" href="css/all.css">
    <link rel="shortcut icon" href="images/m/game-icon.jpeg" type="image/x-icon">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="fix">
            <div class="onexp">
                <span>
                    <img class="icons" src="images/res/strength-icon.png">
                    <span class="charecters">
                        <?php echo $strength; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="images/res/health-icon.png">
                    <span class="charecters">
                        <?php echo $health; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="images/res/shield-icon.png">
                    <span class="charecters"> 
                        <?php echo $defense; ?>
                    </span>
            </span>
            </div>

            <div class="exp">
                <div class="expprog" style="width: <?php echo $exp_progress ?>%;"></div>
            </div>
            </div>
            <h1 class="title">Обменный пункт</h1>
            
 <!-- основной контент --> 

            <!-- Прочие ссылки -->
            <div class="box">
                <div class="links-container">
                
                </div>
                <hr>
                <div class="links-container">
                        <a href="user/user.php" class="link"><img style="background-image: url(images/m/ava-bg.jpg);" src="<?php echo $user_data['avatar_path']; ?>" alt="Аватарка" id="avatar-image">
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
                    <img class="before_footer_icons" src="images/res/up.png" alt="">
                    <span class="charecters">
                        <?php echo $user_data['lvl']; ?> ур. |
                    </span>
                </span>  
                <span>
                    
                    <img class="before_footer_icons" src="images/res/exp.png" alt="">
                    <span class="charecters">
                        <?php echo $exp; ?> опыта |
                    </span>
                </span>  
                <span>
                    <img class="before_footer_icons" src="images/res/coin.png" alt="">
                    <span class="charecters">
                        <?php echo $coin; ?> монет |
                    </span>
                </span>
                <span>
                    <img class="before_footer_icons" src="images/res/golds.png" alt="">
                    <span class="charecters">
                        <?php echo $gold; ?> золотых |
                    </span> 
                </span>
                <span>
                    <img class="before_footer_icons" src="images/res/crystal.png" alt="">
                    <span class="charecters">
                        <?php echo $crystals; ?> крысталлов  
                    </span>
                </span>
                <span>
                    <img class="before_footer_icons" src="../images/res/donut.png" alt="rubles">
                    <span class="charecters">
                        <?php echo htmlspecialchars(formatNumber($user_data['donut'])); ?> рублей 
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
