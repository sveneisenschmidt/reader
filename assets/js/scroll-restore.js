(() => {
    const element = document.querySelector("[data-reading-list]");
    const scrollKey = "reading-list-scroll";
    const activeKey = "reading-list-active";
    const activeElement = document.querySelector("[data-active]");

    if (element) {
        if (activeElement) {
            const savedActive = sessionStorage.getItem(activeKey);
            const currentActive = activeElement.dataset.active;

            // Scroll to active element if it changed (keyboard navigation)
            if (savedActive !== currentActive) {
                activeElement.scrollIntoView({ block: "center" });
                sessionStorage.setItem(activeKey, currentActive);
                sessionStorage.setItem(scrollKey, element.scrollTop);
            } else {
                // Same item, restore scroll position
                const saved = sessionStorage.getItem(scrollKey);
                if (saved) {
                    element.scrollTop = parseInt(saved, 10);
                }
            }
        } else {
            sessionStorage.removeItem(scrollKey);
            sessionStorage.removeItem(activeKey);
        }
        element.addEventListener("scroll", () => {
            sessionStorage.setItem(scrollKey, element.scrollTop);
        });
    }
})();
