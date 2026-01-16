document.addEventListener("keydown", (e) => {
    if (e.target.matches("input, textarea, select")) return;

    const hasActiveItem = document.body.classList.contains("has-active-item");

    switch (e.code) {
        case "Tab": {
            e.preventDefault();
            const items = [
                ...document.querySelectorAll(
                    ".subscription-list > li:not(.folder) > a, .subscription-list .folder-feeds li > a",
                ),
            ];
            const active = document.querySelector(
                ".subscription-list li.active > a",
            );
            const currentIndex = active ? items.indexOf(active) : -1;
            const nextIndex = e.shiftKey
                ? currentIndex <= 0
                    ? items.length - 1
                    : currentIndex - 1
                : currentIndex >= items.length - 1
                  ? 0
                  : currentIndex + 1;
            items[nextIndex]?.click();
            break;
        }

        case "Enter":
            e.preventDefault();
            if (hasActiveItem) {
                document
                    .querySelector("[data-reading-pane] header h1 a")
                    ?.click();
            } else {
                document.querySelector(".feed-item:first-child a")?.click();
            }
            break;

        case "Space":
            if (!hasActiveItem) return;
            e.preventDefault();
            document
                .querySelector(
                    "[data-reading-pane] footer form[data-form-mark-read], [data-reading-pane] footer form[data-form-mark-unread]",
                )
                ?.submit();
            break;

        case "ArrowDown": {
            if (!hasActiveItem) return;
            e.preventDefault();
            const nextItem =
                document.querySelector(".feed-item.active")?.nextElementSibling;
            if (nextItem) {
                nextItem.scrollIntoView({ block: "nearest" });
                nextItem.querySelector("a")?.click();
            }
            break;
        }

        case "ArrowUp": {
            if (!hasActiveItem) return;
            e.preventDefault();
            const prevItem =
                document.querySelector(
                    ".feed-item.active",
                )?.previousElementSibling;
            if (prevItem) {
                prevItem.scrollIntoView({ block: "nearest" });
                prevItem.querySelector("a")?.click();
            }
            break;
        }

        case "Escape":
            if (!hasActiveItem) return;
            e.preventDefault();
            document.querySelector("h1 .home")?.click();
            break;
    }
});
