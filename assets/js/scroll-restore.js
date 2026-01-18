/**
 * Scroll Restore
 *
 * Restores the scroll position of the feed list and sidebar between page loads.
 * Desktop only - mobile uses native scroll restoration.
 */
(function () {
    function setupScrollRestore(element, key) {
        if (!element) return;

        // Restore
        const saved = sessionStorage.getItem(key);
        if (saved) {
            window.requestAnimationFrame(() => {
                element.scrollTop = parseInt(saved, 10);
            });
        }

        // Save
        element.addEventListener("scroll", () => {
            sessionStorage.setItem(key, element.scrollTop);
        });
    }

    // Reading list
    const readingList = document.querySelector("[data-reading-list]");
    if (readingList) {
        const subscription = readingList.dataset.subscription || "all";
        setupScrollRestore(readingList, "scroll:" + subscription);
    }

    // Sidebar
    const sidebar = document.querySelector("[data-sidebar] > ul");
    setupScrollRestore(sidebar, "scroll:sidebar");
})();
