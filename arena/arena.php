<?php
session_start();
require_once '../db.php';
require_once '../level_system.php';
require_once 'ArenaManager.php';

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

// Initialize Arena Manager
$arena = new ArenaManager($conn, $user_data['id']);

// Handle match finding
if (isset($_POST['find_match'])) {
    try {
        $opponent = $arena->findMatch();
        if ($opponent) {
            $match_id = $arena->startMatch($opponent['id']);
            $_SESSION['current_match'] = $match_id;
            $success = "Opponent found! Match starting...";
        } else {
            $error = "No suitable opponents found. Try again later.";
        }
    } catch (Exception $e) {
        $error = "Error finding match: " . $e->getMessage();
    }
}

// Format display values
$strength = formatNumber($user_data['strength']);
$health = formatNumber($user_data['health']);
$defense = formatNumber($user_data['defense']);
$exp = formatNumber($user_data['exp']);
$coin = formatNumber($user_data['coin']);
$gold = formatNumber($user_data['gold']);
$crystals = formatNumber($user_data['crystals']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arena - Eclipse Wars</title>
    <link rel="stylesheet" href="../css/all.css">
    <script src="../js/time.js"></script>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="onexp">
                <span>
                    <img class="icons" src="../images/res/strength-icon.png">
                    <span class="charecters"><?php echo $strength; ?></span>
                </span>
                <span>
                    <img class="icons" src="../images/res/health-icon.png">
                    <span class="charecters"><?php echo $health; ?></span>
                </span>
                <span>
                    <img class="icons" src="../images/res/shield-icon.png">
                    <span class="charecters"><?php echo $defense; ?></span>
                </span>
            </div>
            
            <div class="exp">
                <div class="expprog" style="width: <?php echo $exp_progress ?>%;"></div>
            </div>

            <h1 class="title">Arena</h1>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="arena-container">
                <div class="arena-stats">
                    <h3>Your Arena Stats</h3>
                    <p>Rating: <?php echo $user_data['arena_rating']; ?></p>
                    <p>Wins: <?php echo $user_data['arena_wins']; ?></p>
                    <p>Losses: <?php echo $user_data['arena_losses']; ?></p>
                    <p>Tier: <?php echo ucfirst($user_data['arena_tier'] ?? 'Bronze'); ?></p>
                </div>

                <form method="post" class="arena-controls">
                    <button type="submit" name="find_match" class="btn">Find Match</button>
                </form>

                <div class="arena-rewards">
                    <h3>Current Season Rewards</h3>
                    <div class="reward-tiers">
                        <div class="reward-tier">
                            <h4>Bronze</h4>
                            <p>100 Gold</p>
                        </div>
                        <div class="reward-tier">
                            <h4>Silver</h4>
                            <p>250 Gold</p>
                        </div>
                        <div class="reward-tier">
                            <h4>Gold</h4>
                            <p>500 Gold</p>
                        </div>
                        <div class="reward-tier">
                            <h4>Platinum</h4>
                            <p>1000 Gold</p>
                        </div>
                    </div>
                </div>
            </div>

            <hr>

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

            <hr>

            <div class="in_all">
                <span>
                    <img class="before_footer_icons" src="../images/res/up.png" alt="">
                    <span class="charecters"><?php echo $user_data['lvl']; ?> lvl |</span>
                    <img class="before_footer_icons" src="../images/res/exp.png" alt="">
                    <span class="charecters"><?php echo $exp; ?> exp |</span>
                    <img class="before_footer_icons" src="../images/res/coin.png" alt="">
                    <span class="charecters"><?php echo $coin; ?> coins</span>
                    <img class="before_footer_icons" src="../images/res/golds.png" alt="">
                    <span class="charecters"><?php echo $gold; ?> gold |</span>
                    <img class="before_footer_icons" src="../images/res/crystal.png" alt="">
                    <span class="charecters"><?php echo $crystals; ?> crystals</span>
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

    <script>
        // Auto-refresh for match finding
        <?php if (isset($_SESSION['current_match'])): ?>
        function checkMatchStatus() {
            fetch('check_match.php?match_id=<?php echo $_SESSION['current_match']; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'completed') {
                        window.location.reload();
                    }
                });
        }
        setInterval(checkMatchStatus, 5000);
        <?php endif; ?>
    </script>
</body>
</html>