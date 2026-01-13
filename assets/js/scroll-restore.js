/**
 * Scroll Restore
 *
 * Restores the scroll position of the feed list between page loads.
 * Desktop only - mobile uses native scroll restoration.
 *
 * Why desktop only:
 * - Mobile (<=768px) has a different scroll container (#feed with overflow-y)
 * - Native scrollRestoration works better on mobile Safari
 * - Custom restoration on mobile caused visible flickering
 * - requestAnimationFrame helped but didn't fully solve mobile issues
 *
 * Why DOMContentLoaded:
 * - Script loads in <head> before body is parsed
 * - [data-reading-list] doesn't exist yet at script execution time
 *
 * Why requestAnimationFrame for restore:
 * - Ensures layout is complete before setting scrollTop
 * - Prevents small offset issues on desktop
 *
 * Why subscription-specific keys:
 * - Each feed maintains its own scroll position
 * - Switching subscriptions doesn't restore wrong position
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
