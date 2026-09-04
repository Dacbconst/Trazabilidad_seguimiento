document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.getElementById("sidebar");
    const toggleButton = document.getElementById("toggleSidebar");
    const mainContent = document.querySelector(".main-content");

    toggleButton.addEventListener("click", function() {
        sidebar.classList.toggle("collapsed");
        mainContent.classList.toggle("collapsed");
    });
});
