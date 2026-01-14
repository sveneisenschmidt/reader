// Event delegation: single listener on document instead of one per link.
// Reduces memory overhead when there are many feed items with /open? links.
(function () {
    document.addEventListener("click", (e) => {
        const link = e.target.closest('a[href*="/open?"]');
        if (link) {
            setTimeout(() => {
                window.location.reload();
            }, 100);
        }
    });
})();
