/**
 * Scroll Restore
 *
 * Restores the scroll position of the feed list between page loads.
 *
 * Scroll containers:
 * - Desktop: section[data-reading-list]
 * - Mobile: [data-scroll-container]
 */

(() => {
    const STORAGE_KEY = "reading-list-scroll";
    const isMobile = window.matchMedia("(max-width: 768px)").matches;
    const hasReadingPane = document.body.classList.contains("has-active-item");

    const element = isMobile
        ? document.querySelector("[data-scroll-container]")
        : document.querySelector("[data-reading-list]");

    if (!element) return;

    // On mobile with reading pane open, don't restore or save
    if (isMobile && hasReadingPane) return;

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
})();
