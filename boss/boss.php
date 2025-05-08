<?php
session_start();
require_once '../db.php';
require_once '../level_system.php';
require_once 'BossManager.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../register.php");
    exit();
}

$username = $_SESSION['username'];
$error = '';
$success = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

if (!$user_data) {
    die("User not found");
}

$user_data = processHealthRegeneration($user_data, $conn);
$user_data = checkLevelUp($user_data, $conn);
$exp_progress = getExpProgress($user_data['lvl'], $user_data['exp']);

// Initialize Boss Manager
$bossManager = new BossManager($conn, $user_data['id']);

// Get active bosses
$active_bosses = $bossManager->getActiveBosses();

// Handle boss attack
if (isset($_POST['attack_boss'])) {
    try {
        $boss_id = $_POST['boss_id'];
        $battle_id = $bossManager->joinBossBattle($boss_id);
        $damage = calculateDamage($user_data);
        $remaining_health = $bossManager->attackBoss($battle_id, $damage);
        $success = "Attack successful! Dealt $damage damage.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function calculateDamage($user_data) {
    // Basic damage calculation
    $base_damage = $user_data['strength'];
    $critical_chance = 0.1; // 10% chance
    $critical_multiplier = 1.5;
    
    // Apply random variation (±10%)
    $variation = rand(90, 110) / 100;
    $damage = $base_damage * $variation;
    
    // Check for critical hit
    if (rand(1, 100) <= ($critical_chance * 100)) {
        $damage *= $critical_multiplier;
    }
    
    return round($damage);
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boss Battles - Eclipse Wars</title>
    <link rel="stylesheet" href="../css/all.css">
    <script src="../js/time.js"></script>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="onexp">
                <span>
                    <img class="icons" src="../images/res/strength-icon.png">
                    <span class="charecters"><?php echo $user_data['strength']; ?></span>
                </span>
                <span>
                    <img class="icons" src="../images/res/health-icon.png">
                    <span class="charecters"><?php echo $user_data['health']; ?></span>
                </span>
                <span>
                    <img class="icons" src="../images/res/shield-icon.png">
                    <span class="charecters"><?php echo $user_data['defense']; ?></span>
                </span>
            </div>
            
            <div class="exp">
                <div class="expprog" style="width: <?php echo $exp_progress ?>%;"></div>
            </div>

            <h1 class="title">World Bosses</h1>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="boss-container">
                <?php foreach ($active_bosses as $boss): ?>
                    <div class="boss-card">
                        <img src="<?php echo htmlspecialchars($boss['image']); ?>" 
                             alt="<?php echo htmlspecialchars($boss['name']); ?>" 
                             class="boss-image">
                        <div class="boss-info">
                            <h3><?php echo htmlspecialchars($boss['name']); ?></h3>
                            <div class="boss-stats">
                                <p>Level: <?php echo $boss['level']; ?></p>
                                <p>Health: <?php echo $boss['current_health']; ?>/<?php echo $boss['max_health']; ?></p>
                                <p>Required Level: <?php echo $boss['min_level']; ?></p>
                            </div>
                            <div class="boss-rewards">
                                <h4>Rewards:</h4>
                                <?php 
                                $rewards = json_decode($boss['rewards'], true);
                                echo "<p>Experience: {$rewards['exp']}</p>";
                                echo "<p>Gold: {$rewards['gold']}</p>";
                                echo "<p>Crystals: {$rewards['crystals']}</p>";
                                ?>
                            </div>
                            <form method="post" class="boss-actions">
                                <input type="hidden" name="boss_id" value="<?php echo $boss['id']; ?>">
                                <button type="submit" name="attack_boss" 
                                        class="btn" 
                                        <?php echo $user_data['level'] < $boss['min_level'] ? 'disabled' : ''; ?>>
                                    Attack Boss
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="box">
                <div class="links-container">
                    <a href="../user/user.php" class="link">
                        <img src="../<?php echo $user_data['avatar_path']; ?>" alt="Avatar">
                        <?php echo $username; ?>
                    </a>
                    <a href="../clan/clan.php" class="link">
                        <img src="../images/m/clanss.png" alt="">Clan
                    </a>
                    <a href="../index.php" class="link">
                        <img src="../images/m/main.png" alt="">Main
                    </a>
                </div>
            </div>

            <div class="in_all">
                <span>
                    <img class="before_footer_icons" src="../images/res/up.png" alt="">
                    <span class="charecters"><?php echo $user_data['lvl']; ?> lvl |</span>
                    <img class="before_footer_icons" src="../images/res/exp.png" alt="">
                    <span class="charecters"><?php echo $user_data['exp']; ?> exp |</span>
                    <img class="before_footer_icons" src="../images/res/coin.png" alt="">
                    <span class="charecters"><?php echo $user_data['coin']; ?> coins</span>
                    <img class="before_footer_icons" src="../images/res/golds.png" alt="">
                    <span class="charecters"><?php echo $user_data['gold']; ?> gold |</span>
                    <img class="before_footer_icons" src="../images/res/crystal.png" alt="">
                    <span class="charecters"><?php echo $user_data['crystals']; ?> crystals</span>
                </span>
            </div>
        </div>

        <footer>
            <a href="../chat/chat.php" class="soc">Chat</a> |
            <a href="../forum/forum.php" class="soc">Forum</a> |
            <a href="#" style="color: aquamarine;">Promo</a>
            <p>&copy; 2025, 16+ | +200% crystals and coins!</p>
            <p><span id="serverTime"></span> | <a href="../logout.php" style="color: #a5a5a5;">Logout</a></p>
        </footer>
    </div>
</body>
</html>