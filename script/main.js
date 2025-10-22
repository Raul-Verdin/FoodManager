document.getElementById("userMenuBtn").addEventListener("click", function() {
    let popup = document.getElementById("userPopup");
    popup.style.display = popup.style.display === "block" ? "none" : "block";
});

// Cerrar si clickea fuera
window.addEventListener("click", function(event) {
    let popup = document.getElementById("userPopup");
    let btn = document.getElementById("userMenuBtn");
    if (event.target !== popup && event.target !== btn) {
        popup.style.display = "none";
    }
});