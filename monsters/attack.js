// В attack.js
document.getElementById("attack-form").addEventListener("submit", function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    console.log("Sending monster_id:", formData.get('monster_id')); // Добавить лог
    
    fetch('attack.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) alert(data.error);
        else {
            document.getElementById("monster-hp").textContent = data.monster_hp;
            document.getElementById("player-hp").textContent = data.player_hp;
        }
    })
    .catch(error => console.error("Error:", error));
});

const attackForm = document.getElementById("attack-form");
if (attackForm) {
    attackForm.addEventListener("submit", function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        console.log("Sending monster_id:", formData.get('monster_id')); // Добавить лог

        fetch('attack.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) alert(data.error);
            else {
                document.getElementById("monster-hp").textContent = data.monster_hp;
                document.getElementById("player-hp").textContent = data.player_hp;
            }
        })
        .catch(error => console.error("Error:", error));
    });
} else {
    console.warn("Форма атаки (attack-form) не найдена на этой странице.");
}

// attack.js