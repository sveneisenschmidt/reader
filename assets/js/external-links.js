document
    .querySelectorAll("[data-external-link], [data-article-content] a")
    .forEach((el) => {
        el.setAttribute("target", "_blank");
        el.addEventListener("click", (e) => {
            const form = document.querySelector("[data-mark-read-stay-form]");
            if (form) {
                if (el.hasAttribute("data-external-link")) {
                    e.preventDefault();
                    window.open(form.dataset.externalUrl, "_blank");
                }
                form.submit();
            }
        });
    });
