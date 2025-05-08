// Функция для обновления здоровья
function updateHealth() {
    fetch('../api/get_health.php')
        .then(response => response.json())
        .then(data => {
            const healthElement = document.querySelector('.health-display');
            if (healthElement) {
                healthElement.textContent = data.health;
                
                // Продолжаем обновлять, если здоровье не полное
                if (data.health < data.max_health) {
                    setTimeout(updateHealth, 1000);
                }
            }
        })
        .catch(error => console.error('Ошибка:', error));
}

// Запускаем при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Проверяем, нужно ли начать обновление
    const healthElement = document.querySelector('.health-display');
    if (healthElement) {
        const currentHealth = parseInt(healthElement.textContent);
        const maxHealth = <?php echo $user_data['max_health'] ?? 100; ?>;
        
        if (currentHealth < maxHealth) {
            updateHealth();
        }
    }
});