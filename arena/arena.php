<?php
session_start();
require_once '../db.php';
require_once '../level_system.php';
require_once 'battle_system.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../register.php");
    exit();
}

$username = $_SESSION['username'];
$error = '';
$success = '';
$battle_log = [];
$opponent = null;
$is_pvp = false;

// Получаем данные пользователя
$user_data = getUserData($conn, $username);
$user_data = processHealthRegeneration($user_data, $conn);



$strength = formatNumber($user_data['strength']);
$health = formatNumber($user_data['health']);
$defense = formatNumber($user_data['defense']);
$exp = formatNumber($user_data['exp']);
$coin = formatNumber($user_data['coin']);
$gold = formatNumber($user_data['gold']);
$crystals = formatNumber($user_data['crystals']);
$donut = formatNumber($user_data['donut'] ?? 0);

// Обработка боя на арене
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['find_pvp_opponent'])) {
        $opponent = findPvPOpponent($conn, $user_data['lvl']);
        $is_pvp = true;
        
        if (!$opponent) {
            $error = "Не найдено подходящих соперников. Попробуйте позже.";
        } else {
            $success = "Найден соперник: ".$opponent['username']." (Уровень ".$opponent['lvl'].")";
        }
    }
    elseif (isset($_POST['fight'])) {
        $difficulty = $_POST['difficulty'] ?? 'medium';
        
        if (!$is_pvp) {
            $opponent = generateOpponent($user_data['lvl'], $difficulty);
        }
        
        if ($user_data['health'] < 10) {
            $error = "Недостаточно здоровья для боя!";
        } else {
            $battle_result = simulateBattle($user_data, $opponent, $is_pvp);
            $battle_log = $battle_result['log'];
            
            processBattleResult($conn, $username, $battle_result, $is_pvp);
            
            $user_data = getUserData($conn, $username);
            
            if ($battle_result['won']) {
                $reward_text = $is_pvp ? "Поздравляем! Вы победили в PvP бою и получаете " : "Победа! Вы получаете ";
                $success = $reward_text . $battle_result['reward']['exp']." опыта и ".$battle_result['reward']['gold']." золота";
                
                if ($is_pvp && $battle_result['reward']['crystals'] > 0) {
                    $success .= ", а также ".$battle_result['reward']['crystals']." кристаллов";
                }
                $success .= "!";
            } else {
                $error = "Поражение! Вы потеряли ".$battle_result['health_lost']." здоровья.";
            }
        }
    }
}

$user_data = checkLevelUp($user_data, $conn);
$exp_progress = getExpProgress($user_data['lvl'], $user_data['exp']);

// Получаем топ игроков и историю боев
$top_players = getArenaTopPlayers($conn);
$battle_history = getBattleHistory($conn, $username);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Арена - Войны Затмения</title>
    <link rel="stylesheet" href="../css/all.css">
    <link rel="stylesheet" href="../css/arena.css">
    <link rel="shortcut icon" href="../images/m/game-icon.jpeg" type="image/x-icon">
    <style>
        .tab-content { display: none; }
        .tab-content.active-tab { display: block; }
        .tab-btn.active { font-weight: bold; background: #444; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="fix">
            <div class="onexp">
                <span>
                    <img class="icons" src="../images/res/strength-icon.png">
                    <span class="charecters">
                        <?php echo $strength; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/health-icon.png">
                    <span class="charecters">
                        <?php echo $health; ?>
                    </span>
                </span>
                <span>
                    <img class="icons" src="../images/res/shield-icon.png">
                    <span class="charecters"> 
                        <?php echo $defense; ?>
                    </span>
            </span>
            </div>

            <div class="exp">
                <div class="expprog" style="width: <?php echo $exp_progress ?>%;"></div>
            </div>
            </div>
            <h1 class="title">Арена</h1>
            
            <!-- Основной контент -->
            <div class="arena-tabs">
                <button class="tab-btn active" onclick="openTab('pve')">PvE</button>
                <button class="tab-btn" onclick="openTab('pvp')">PvP</button>
                <button class="tab-btn" onclick="openTab('top')">Топ игроков</button>
                <button class="tab-btn" onclick="openTab('history')">История боев</button>
            </div>
            
            <div id="pve-tab" class="tab-content active-tab">
                <div class="arena-container">
                    <!-- Блок игрока -->
                    <div class="fighter player">
                        <div class="fighter-header">
                            <img src="<?= $user_data['avatar_path'] ?>" alt="Игрок">
                            <h3><?= $username ?></h3>
                        </div>
                        <div class="fighter-stats">
                            <p>Уровень: <?= $user_data['lvl'] ?></p>
                            <div class="stat-bar">
                                <label>Здоровье:</label>
                                <div class="bar-container">
                                    <div class="bar health" style="width: <?= ($user_data['health'] / $user_data['max_health']) * 100 ?>%"></div>
                                    <span><?= $user_data['health'] ?>/<?= $user_data['max_health'] ?></span>
                                </div>
                            </div>
                            <div class="stat-bar">
                                <label>Сила:</label>
                                <div class="bar strength" style="width: <?= min(100, $user_data['strength']) ?>%"></div>
                            </div>
                            <div class="stat-bar">
                                <label>Защита:</label>
                                <div class="bar defense" style="width: <?= min(100, $user_data['defense']) ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Блок противника -->
                    <div class="fighter opponent">
                        <?php if ($opponent && !$is_pvp): ?>
                            <div class="fighter-header">
                                <img src="images/opponents/<?= $opponent['image'] ?>.jpg" alt="Противник">
                                <h3><?= $opponent['name'] ?></h3>
                            </div>
                            <div class="fighter-stats">
                                <p>Уровень: <?= $opponent['lvl'] ?></p>
                                <div class="stat-bar">
                                    <label>Здоровье:</label>
                                    <div class="bar-container">
                                        <div class="bar health" style="width: <?= $battle_result['won'] ? 0 : 100 ?>%"></div>
                                        <span><?= $opponent['health'] ?>/<?= $opponent['health'] ?></span>
                                    </div>
                                </div>
                                <div class="stat-bar">
                                    <label>Сила:</label>
                                    <div class="bar strength" style="width: <?= min(100, $opponent['strength']) ?>%"></div>
                                </div>
                                <div class="stat-bar">
                                    <label>Защита:</label>
                                    <div class="bar defense" style="width: <?= min(100, $opponent['defense']) ?>%"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="select-opponent">
                                <h3>Выберите сложность:</h3>
                                <form method="post">
                                    <div class="difficulty-buttons">
                                        <button type="button" class="difficulty-btn easy" onclick="selectDifficulty('easy')">Легкий</button>
                                        <button type="button" class="difficulty-btn medium" onclick="selectDifficulty('medium')">Средний</button>
                                        <button type="button" class="difficulty-btn hard" onclick="selectDifficulty('hard')">Тяжелый</button>
                                    </div>
                                    <input type="hidden" name="difficulty" id="selected-difficulty" value="medium">
                                    <button type="submit" name="fight" class="btn-fight">Сражаться!</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Лог боя -->
                <?php if (!empty($battle_log)): ?>
                    <div class="battle-log">
                        <h3>Ход боя:</h3>
                        <div class="log-content">
                            <?php foreach ($battle_log as $log): ?>
                                <div class="log-entry <?= $log['type'] ?>">
                                    <?= $log['text'] ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- PvP вкладка -->
            <div id="pvp-tab" class="tab-content">
                <?php if ($is_pvp && $opponent): ?>
                    <div class="arena-container pvp">
                        [Аналогичный блок боя, но с PvP оформлением]
                    </div>
                <?php else: ?>
                    <div class="pvp-info">
                        <h3>PvP Арена</h3>
                        <p>Сразитесь с реальными игроками и получите уникальные награды!</p>
                        <div class="pvp-rewards">
                            <div class="reward-card">
                                <img src="images/res/golds.png" alt="Золото">
                                <p>+50% золота</p>
                            </div>
                            <div class="reward-card">
                                <img src="images/res/crystal.png" alt="Кристаллы">
                                <p>Кристаллы за победу</p>
                            </div>
                            <div class="reward-card">
                                <img src="images/res/rating.png" alt="Рейтинг">
                                <p>Рейтинговые очки</p>
                            </div>
                        </div>
                        <form method="post">
                            <button type="submit" name="find_pvp_opponent" class="btn-pvp">Найти соперника</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Топ игроков -->
            <div id="top-tab" class="tab-content">
                <h3>Топ-10 игроков арены</h3>
                <div class="top-players">
                    <?php foreach ($top_players as $index => $player): ?>
                        <div class="player-card <?= $index < 3 ? 'top'.($index+1) : '' ?>">
                            <div class="player-rank"><?= $index+1 ?></div>
                            <img src="<?= $player['avatar_path'] ?>" alt="<?= $player['username'] ?>">
                            <div class="player-info">
                                <h4><?= $player['username'] ?></h4>
                                <p>Уровень: <?= $player['lvl'] ?></p>
                                <p>Побед: <?= $player['pvp_wins'] ?></p>
                                <p>Рейтинг: <?= $player['arena_rating'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- История боев -->
            <div id="history-tab" class="tab-content">
                <h3>Последние бои</h3>
                <?php $battle_history = getBattleHistory($conn, $username); ?>
                <div class="history-list">
                    <?php if (empty($battle_history)): ?>
                        <p>У вас еще не было боев на арене.</p>
                    <?php else: ?>
                        <?php foreach ($battle_history as $battle): ?>
                            <div class="history-item <?= $battle['won'] ? 'victory' : 'defeat' ?>">
                                <div class="battle-result"><?= $battle['won'] ? 'Победа' : 'Поражение' ?></div>
                                <div class="battle-opponent">
                                    <?= $battle['is_pvp'] ? 'vs '.$battle['opponent_name'] : 'vs '.$battle['opponent_name'].' (PvE)' ?>
                                </div>
                                <div class="battle-rewards">
                                    <?php if ($battle['won']): ?>
                                        <span>+<?= $battle['exp_gained'] ?> опыта</span>
                                        <span>+<?= $battle['gold_gained'] ?> золота</span>
                                        <?php if ($battle['crystals_gained'] > 0): ?>
                                            <span>+<?= $battle['crystals_gained'] ?> кристаллов</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span>-<?= $battle['health_lost'] ?> здоровья</span>
                                    <?php endif; ?>
                                </div>
                                <div class="battle-date"><?= $battle['battle_date'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Прочие -->
            <div class="box">
                <div class="links-container">
                
                </div>
                <hr>
                <div class="links-container">
                        <a href="user/user.php" class="link"><img style="background-image: url(../images/m/ava-bg.jpg);" src="<?php echo $user_data['avatar_path']; ?>" alt="Аватарка" id="avatar-image">
                            <?php echo $username;?>
                        </a>
                        <?php 
                            $links = [
                                ['href' => 'myclan.html', 'img' => 'm/clanss.png', 'text' => 'Клан'],
                                ['href' => '../index.php', 'img' => 'm/main.png', 'text' => 'Главная'],
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
                    <img class="before_footer_icons" src="../images/res/up.png" alt="">
                    <span class="charecters">
                        <?php echo $user_data['lvl']; ?> ур. |
                    </span>
                </span>  
                <span>
                    
                    <img class="before_footer_icons" src="../images/res/exp.png" alt="">
                    <span class="charecters">
                        <?php echo $exp; ?> опыта |
                    </span>
                </span>  
                <span>
                    <img class="before_footer_icons" src="../images/res/coin.png" alt="">
                    <span class="charecters">
                        <?php echo $coin; ?> монет |
                    </span>
                </span>
                <span>
                    <img class="before_footer_icons" src="../images/res/golds.png" alt="">
                    <span class="charecters">
                        <?php echo $gold; ?> золотых |
                    </span> 
                </span>
                <span>
                    <img class="before_footer_icons" src="../images/res/crystal.png" alt="">
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
    
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Функция переключения вкладок
        function openTab(tabName, event) {
            event.preventDefault();
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active-tab');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(tabName+'-tab').classList.add('active-tab');
            event.currentTarget.classList.add('active');
        }

        // Назначение обработчиков
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const tabName = this.getAttribute('onclick').match(/openTab\('([^']+)'/)[1];
                openTab(tabName, e);
            });
        });

        // Выбор сложности
        function selectDifficulty(difficulty) {
            document.getElementById('selected-difficulty').value = difficulty;
        }

        // Назначение обработчиков сложности
        document.querySelectorAll('.difficulty-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const difficulty = this.getAttribute('onclick').match(/selectDifficulty\('([^']+)'/)[1];
                selectDifficulty(difficulty);
            });
        });
    });
    </script>
</body>
</html>

