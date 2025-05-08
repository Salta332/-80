// document.addEventListener("DOMContentLoaded", function() {
//     let notification = document.getElementById("notification");
//     if (notification) {
//         // Добавляем класс "show" через небольшой таймер, чтобы запустить анимацию
//         setTimeout(() => {
//             notification.classList.add("show");
//         }, 50);

//         // Через 3 секунды скрываем уведомление
//         setTimeout(() => {
//             notification.classList.add("hide");
            
//             // Полностью удаляем элемент через 0.5 секунды
//             setTimeout(() => notification.remove(), 500);
//         }, 1500);
//     }
// });


document.addEventListener('DOMContentLoaded', function() {
    const messageContainer = document.getElementById('messageContainer');
    if (messageContainer) {
        messageContainer.style.display = 'block';
    }
});