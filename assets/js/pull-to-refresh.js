(() => {
    const SCROLL_KEY = "feed-stream-scroll";
    const SPINNER_FRAMES = ["⠋", "⠙", "⠹", "⠸", "⠼", "⠴", "⠦", "⠧", "⠇", "⠏"];

    // Standard thresholds based on PullToRefresh.js defaults
    const DIST_THRESHOLD = 60; // Minimum distance to trigger refresh
    const DIST_MAX = 80; // Maximum pull distance
    const DIST_IGNORE = 20; // Distance before pull starts
    const RESISTANCE = 2.5; // Drag resistance factor

    let refreshing = false;
    let spinnerIndex = 0;
    let spinnerInterval = null;
    let pullIndicator = null;
    let hideIndicatorTimeout = null;

    const createPullIndicator = () => {
        clearTimeout(hideIndicatorTimeout);
        if (pullIndicator) return pullIndicator;
        pullIndicator = document.createElement("div");
        pullIndicator.setAttribute("data-pull-indicator", "");
        pullIndicator.textContent = SPINNER_FRAMES[0];
        document.body.appendChild(pullIndicator);
        return pullIndicator;
    };

    const updatePullIndicator = (progress, ready = false) => {
        const indicator = createPullIndicator();
        const frameIndex = Math.min(
            Math.floor(progress * SPINNER_FRAMES.length),
            SPINNER_FRAMES.length - 1,
        );
        const text = ready ? "Release to refresh" : "Pull to refresh";
        indicator.textContent = SPINNER_FRAMES[frameIndex] + " " + text;
        indicator.style.opacity = Math.min(progress + 0.3, 1);
    };

    const hidePullIndicator = (immediate = false) => {
        if (immediate) {
            clearTimeout(hideIndicatorTimeout);
            if (pullIndicator) {
                pullIndicator.remove();
                pullIndicator = null;
            }
            return;
        }
        hideIndicatorTimeout = setTimeout(() => {
            if (pullIndicator) {
                pullIndicator.remove();
                pullIndicator = null;
            }
        }, 50);
    };

    const applyResistance = (distance) => {
        return Math.min(DIST_MAX, distance / RESISTANCE);
    };

    const startRefresh = () => {
        if (refreshing) return;
        refreshing = true;
        hidePullIndicator(true);

        document.body.classList.add("refreshing");
        const overlay = document.createElement("div");
        overlay.setAttribute("data-refresh-overlay", "");
        overlay.textContent = SPINNER_FRAMES[0];
        document.body.appendChild(overlay);

        spinnerInterval = setInterval(() => {
            spinnerIndex = (spinnerIndex + 1) % SPINNER_FRAMES.length;
            overlay.textContent = SPINNER_FRAMES[spinnerIndex];
        }, 150);

        fetch("/refresh", { method: "POST" }).finally(() => {
            clearInterval(spinnerInterval);
            sessionStorage.removeItem(SCROLL_KEY);

            const path = location.pathname;
            if (path.includes("/f/")) {
                location.href = path.split("/f/")[0] || "/";
            } else {
                location.reload();
            }
        });
    };

    // Touch (mobile)
    let touchStart = 0;
    let touchY = 0;

    document.addEventListener(
        "touchstart",
        (e) => {
            touchStart = e.touches[0].clientY;
            touchY = touchStart;
        },
        { passive: true },
    );

    document.addEventListener(
        "touchmove",
        (e) => {
            touchY = e.touches[0].clientY;
            const rawDistance = touchY - touchStart;
            const atTop = window.scrollY === 0;

            if (atTop && rawDistance > DIST_IGNORE && !refreshing) {
                e.preventDefault();
                const distance = applyResistance(rawDistance - DIST_IGNORE);
                const progress = Math.min(distance / DIST_THRESHOLD, 1);
                const ready = distance >= DIST_THRESHOLD;
                updatePullIndicator(progress, ready);
            } else {
                hidePullIndicator();
            }
        },
        { passive: false },
    );

    document.addEventListener("touchend", () => {
        const rawDistance = touchY - touchStart;
        const atTop = window.scrollY === 0;
        const distance = applyResistance(rawDistance - DIST_IGNORE);

        if (atTop && distance >= DIST_THRESHOLD) {
            startRefresh();
        } else {
            hidePullIndicator();
        }
    });

    // Trackpad/wheel (desktop)
    let overscroll = 0;
    const feedStream = document.querySelector("[data-feed-stream]");
    let atTopSince = 0;
    let releaseTimeout = null;
    let canTrigger = false;

    document.addEventListener(
        "wheel",
        (e) => {
            if (refreshing) return;

            // Ignore scroll events from reading pane content
            let target = e.target;
            while (target) {
                if (target.classList?.contains("content")) {
                    return;
                }
                target = target.parentElement;
            }

            const atTop =
                window.scrollY === 0 &&
                (!feedStream || feedStream.scrollTop === 0);

            if (!atTop) {
                overscroll = 0;
                atTopSince = 0;
                canTrigger = false;
                hidePullIndicator();
                return;
            }

            if (atTopSince === 0) {
                atTopSince = Date.now();
            }

            const atTopLongEnough = Date.now() - atTopSince > 150;

            if (e.deltaY < 0 && atTopLongEnough) {
                overscroll += Math.abs(e.deltaY);

                if (overscroll > DIST_IGNORE) {
                    const distance = applyResistance(overscroll - DIST_IGNORE);
                    const progress = Math.min(distance / DIST_THRESHOLD, 1);
                    canTrigger = distance >= DIST_THRESHOLD;
                    updatePullIndicator(progress, canTrigger);
                }

                // Trigger on release (no scroll events for 150ms)
                clearTimeout(releaseTimeout);
                releaseTimeout = setTimeout(() => {
                    if (canTrigger && !refreshing) {
                        startRefresh();
                    } else {
                        overscroll = 0;
                        canTrigger = false;
                        hidePullIndicator();
                    }
                }, 150);
            }
        },
        { passive: true },
    );
})();
