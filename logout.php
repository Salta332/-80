<?php
session_start();
session_unset(); // Удалить все переменные сессии
session_destroy(); // Уничтожить сессию
header("Location: about.php"); // Перенаправление на страницу регистрации
exit();
?>