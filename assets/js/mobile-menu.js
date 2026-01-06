document.querySelector("[data-menu-toggle]")?.addEventListener("click", (e) => {
    e.preventDefault();
    document.body.classList.toggle("sidebar-open");
});
