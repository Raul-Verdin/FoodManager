document.addEventListener("DOMContentLoaded", function () {
  const toggleBtn = document.getElementById("menuToggle");
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("overlay");

  toggleBtn.addEventListener("click", () => {
    if (window.innerWidth <= 768) {
      sidebar.classList.toggle("active");
      overlay.style.display = sidebar.classList.contains("active") ? "block" : "none";
    }
  });

  overlay.addEventListener("click", () => {
    sidebar.classList.remove("active");
    overlay.style.display = "none";
  });
});
