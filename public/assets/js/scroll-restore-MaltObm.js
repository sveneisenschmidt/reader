(() => {
    const element = document.querySelector("[data-feed-stream]");
    const key = "feed-stream-scroll";
    const activeElement = document.querySelector("[data-active]");

    if (element) {
        if (activeElement) {
            const saved = sessionStorage.getItem(key);
            if (saved) element.scrollTop = parseInt(saved, 10);
        } else {
            sessionStorage.removeItem(key);
        }
        element.addEventListener("scroll", () => {
            sessionStorage.setItem(key, element.scrollTop);
        });
    }
})();
