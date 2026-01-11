// Mark article as read (and stay) when clicking external links
document
    .querySelectorAll("[data-external-link], [data-article-content] a")
    .forEach((el) => {
        el.addEventListener("click", () => {
            const form = document.querySelector("[data-mark-read-stay-form]");
            if (form) {
                form.submit();
            }
        });
    });
