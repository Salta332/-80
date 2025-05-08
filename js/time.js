function updateServerTime() {
    const now = new Date();
    const options = { timeZone: 'Europe/Moscow', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    const timeString = now.toLocaleTimeString('ru-RU', options);
    document.getElementById('serverTime').textContent = timeString;
}
setInterval(updateServerTime, 1000);
updateServerTime();