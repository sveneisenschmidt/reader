(() => {
    const SPINNER = ["⠋", "⠙", "⠹", "⠸", "⠼", "⠴", "⠦", "⠧", "⠇", "⠏"];
    const THRESHOLD = 100;
    const MAX_PULL = 120;
    const RESISTANCE = 2.5;

    let startY = 0;
    let currentY = 0;
    let pulling = false;
    let indicator = null;

    const createIndicator = () => {
        if (indicator) return indicator;
        indicator = document.createElement("div");
        indicator.setAttribute("data-pull-indicator", "");
        indicator.textContent = SPINNER[0];
        document.body.appendChild(indicator);
        return indicator;
    };

    const removeIndicator = () => {
        indicator?.remove();
        indicator = null;
    };

    const updateIndicator = (distance, ready) => {
        const el = createIndicator();
        const progress = Math.min(distance / THRESHOLD, 1);
        const frameIndex = Math.min(
            Math.floor(progress * SPINNER.length),
            SPINNER.length - 1,
        );
        el.textContent = `${SPINNER[frameIndex]} ${ready ? "Release to refresh" : "Pull to refresh"}`;
        el.setAttribute("data-pull-indicator", ready ? "ready" : "pulling");
    };

    const triggerRefresh = () => {
        removeIndicator();
        document.querySelector("[data-refresh-form]")?.requestSubmit();
    };

    // Touch (mobile)
    const getScrollableParent = (target) => {
        while (target) {
            if (
                target.scrollHeight > target.clientHeight &&
                target.scrollTop > 0
            ) {
                return target;
            }
            target = target.parentElement;
        }
        return null;
    };

    document.addEventListener(
        "touchstart",
        (e) => {
            const scrollableParent = getScrollableParent(e.target);
            if (window.scrollY === 0 && !scrollableParent) {
                startY = e.touches[0].clientY;
                pulling = true;
            }
        },
        { passive: true },
    );

    document.addEventListener(
        "touchmove",
        (e) => {
            if (!pulling) return;

            currentY = e.touches[0].clientY;
            const rawDistance = currentY - startY;

            if (window.scrollY === 0 && rawDistance > 0) {
                const distance = Math.min(MAX_PULL, rawDistance / RESISTANCE);
                const ready = distance >= THRESHOLD;
                updateIndicator(distance, ready);
            } else {
                removeIndicator();
            }
        },
        { passive: true },
    );

    document.addEventListener("touchend", () => {
        if (!pulling) return;

        const rawDistance = currentY - startY;
        const distance = Math.min(MAX_PULL, rawDistance / RESISTANCE);

        if (window.scrollY === 0 && distance >= THRESHOLD) {
            triggerRefresh();
        } else {
            removeIndicator();
        }

        pulling = false;
        startY = 0;
        currentY = 0;
    });

    // Trackpad/wheel (desktop)
    const readingList = document.querySelector("[data-reading-list]");
    let overscroll = 0;
    let atTopSince = 0;
    let releaseTimeout = null;
    let canTrigger = false;

    document.addEventListener(
        "wheel",
        (e) => {
            // Ignore scroll events from reading pane content
            let target = e.target;
            while (target) {
                if (target.hasAttribute?.("data-reading-pane")) return;
                target = target.parentElement;
            }

            const atTop =
                window.scrollY === 0 &&
                (!readingList || readingList.scrollTop === 0);

            if (!atTop) {
                overscroll = 0;
                atTopSince = 0;
                canTrigger = false;
                removeIndicator();
                return;
            }

            if (atTopSince === 0) atTopSince = Date.now();
            const atTopLongEnough = Date.now() - atTopSince > 150;

            if (e.deltaY < 0 && atTopLongEnough) {
                overscroll += Math.abs(e.deltaY);

                if (overscroll > 20) {
                    const distance = Math.min(
                        MAX_PULL,
                        (overscroll - 20) / RESISTANCE,
                    );
                    const progress = Math.min(distance / THRESHOLD, 1);
                    canTrigger = distance >= THRESHOLD;
                    updateIndicator(distance, canTrigger);
                }

                clearTimeout(releaseTimeout);
                releaseTimeout = setTimeout(() => {
                    if (canTrigger) {
                        triggerRefresh();
                    } else {
                        removeIndicator();
                    }
                    overscroll = 0;
                    canTrigger = false;
                }, 150);
            }
        },
        { passive: true },
    );
})();
