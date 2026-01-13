(() => {
    const section = document.querySelector("[data-reading-list]");

    // Find the scrollable parent element
    const findScrollableParent = (el) => {
        while (el && el !== document.body) {
            const style = getComputedStyle(el);
            const overflowY = style.overflowY;
            if (
                (overflowY === "auto" || overflowY === "scroll") &&
                el.scrollHeight > el.clientHeight
            ) {
                return el;
            }
            el = el.parentElement;
        }
        return null;
    };

    const element = findScrollableParent(section) || section;
    const scrollKey = "reading-list-scroll";
    const activeKey = "reading-list-active";
    const activeElement = document.querySelector("[data-active]");

    const restoreScroll = () => {
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
            // No active element (back to list) - restore scroll position
            const saved = sessionStorage.getItem(scrollKey);
            if (saved) {
                element.scrollTop = parseInt(saved, 10);
            }
            // Clear the active key since we're back to list view
            sessionStorage.removeItem(activeKey);
        }
    };

    if (element) {
        // Use requestAnimationFrame to ensure layout is complete (iOS Safari)
        requestAnimationFrame(() => {
            requestAnimationFrame(restoreScroll);
        });

        element.addEventListener("scroll", () => {
            sessionStorage.setItem(scrollKey, element.scrollTop);
        });
    }
})();
