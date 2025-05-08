<?php

// Установка соединения с базой данных
$servername = "localhost";
$username_db = "root";
$password_db = "root";
$dbname = "apocalypsedb";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Проверка соединения
if ($conn->connect_error) {
    die("Ошибка подключения: " . $conn->connect_error);
}

?>