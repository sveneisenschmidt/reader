/**
 * Scroll Restore
 *
 * Restores the scroll position of the feed list between page loads.
 * Desktop only - mobile uses native scroll restoration.
 */

if (!window.matchMedia("(max-width: 768px)").matches) {
    document.addEventListener("DOMContentLoaded", () => {
        const element = document.querySelector("[data-reading-list]");
        if (!element) return;

        // Use subscription as key so each feed has its own scroll position
        const subscription = element.dataset.subscription || "all";
        const STORAGE_KEY = "scroll:" + subscription;

        // Restore
        const saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved) {
            window.requestAnimationFrame(() => {
                element.scrollTop = parseInt(saved, 10);
            });
        }

        // Save
        element.addEventListener("scroll", () => {
            sessionStorage.setItem(STORAGE_KEY, element.scrollTop);
        });
    });
}
