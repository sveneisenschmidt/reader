document.addEventListener("DOMContentLoaded", () => {
    console.log("[open-link-refresh] v2 loaded");
    document.querySelectorAll('a[href*="/open?"]').forEach((link) => {
        link.addEventListener("click", () => {
            setTimeout(() => {
                window.location.reload();
            }, 100);
        });
    });
});
