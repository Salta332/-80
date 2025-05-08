<?php
session_start();
require_once '../db.php';

// Проверка авторизации
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'Пользователь не авторизован.']);
    exit();
}

$username = $_SESSION['username'];

// Получаем текущий путь к аватарке
$user_query = "SELECT avatar_path, gender FROM users WHERE username = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Удаляем старую аватарку, если она существует
if (!empty($user_data['avatar_path']) && file_exists($user_data['avatar_path'])) {
    unlink($user_data['avatar_path']);
}

// Обработка загрузки новой аватарки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $targetDir = "uploads/avatars/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true); // Создаем папку, если её нет
    }

    // Генерация уникального имени файла
    $fileName = uniqid() . '_' . basename($_FILES['avatar']['name']);
    $targetFile = $targetDir . $fileName;

    // Проверка файла
    $check = getimagesize($_FILES['avatar']['tmp_name']);
    if ($check === false) {
        echo json_encode(['success' => false, 'error' => 'Файл не является изображением.']);
        exit();
    }

    // Проверка размера файла (например, до 5MB)
    if ($_FILES['avatar']['size'] > 5000000) {
        echo json_encode(['success' => false, 'error' => 'Файл слишком большой.']);
        exit();
    }

    // Разрешенные форматы файлов
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    if (!in_array($imageFileType, ['jpg', 'jpeg', 'png'])) {
        echo json_encode(['success' => false, 'error' => 'Разрешены только JPG, JPEG и PNG файлы.']);
        exit();
    }

    // Сохраняем файл на сервере
    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
        // Сохраняем путь к файлу в БД
        $update_query = "UPDATE users SET avatar_path = ? WHERE username = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ss", $targetFile, $username);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ошибка при обновлении аватара в базе данных.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при загрузке файла.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Файл не был загружен.']);
}
?>